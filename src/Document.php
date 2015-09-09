<?php
namespace Phidias\Json;

class Document
{
    private $documentRoot;
    private $filename;
    private $object;

    private static $cache;

    public function __construct($filename, $documentRoot = null)
    {
        if (!is_file($filename)) {
            trigger_error("JSON Document: invalid filename '$filename'", E_USER_ERROR);
        }

        $hash = md5(realpath($filename));
        if (isset(self::$cache[$hash])) {
            $this->filename     = self::$cache[$hash]->filename;
            $this->documentRoot = self::$cache[$hash]->documentRoot;
            $this->object       = self::$cache[$hash]->object;
            return;
        }

        $this->object = json_decode(file_get_contents($filename));
        if ($this->object == null) {
            trigger_error("JSON Document: invalid JSON in '$filename'", E_USER_ERROR);
        }

        self::$cache[$hash] = $this;

        $this->filename     = $filename;
        $this->documentRoot = $documentRoot ? $documentRoot : dirname($this->filename);
        $this->object       = $this->hidrate($this->object);

    }

    public static function parse($filename)
    {
        return (new Document($filename))->object;
    }

    private function hidrate($object)
    {
        if (is_scalar($object)) {
            return $object;
        }

        if (is_array($object)) {
            foreach ($object as &$item) {
                $item = $this->hidrate($item);
            }
            return $object;
        }

        $ref = '$ref';
        if (isset($object->$ref)) {

            $referencedObject = $this->fetchReference($object->$ref);
            if (is_object($referencedObject)) {
                self::merge($object, $referencedObject);
                unset($object->$ref);
            } else {
                return $referencedObject;
            }

        }

        foreach (get_object_vars($object) as $propertyName => $propertyValue) {
            $object->$propertyName = $this->hidrate($propertyValue);
        }

        return $object;
    }

    private function fetchReference($reference)
    {
        $parts = explode("#", $reference);
        $referencedFilename = isset($parts[0]) ? $parts[0] : null;
        $referencedProperty = isset($parts[1]) ? trim($parts[1], "/") : null;

        if ($referencedFilename) {

            if (substr($reference, 0, 1) == "/") {
                $path = $this->documentRoot."/".$referencedFilename;
            } else {
                $path = dirname($this->filename)."/".$referencedFilename;
            }

            $referencedDocument = new Document($path, $this->documentRoot);

        } else {
            $referencedDocument = $this;
        }

        return $referencedDocument->getProperty($referencedProperty);

    }

    public function getProperty($propertyName)
    {
        $propertyName = trim($propertyName, "/");
        $current      = $this->object;

        foreach (explode("/", $propertyName) as $property) {

            if (! $property = trim($property)) {
                continue;
            }

            if (!isset($current->$property)) {
                return "broken property $propertyName (no $property)";
            }

            $current = $current->$property;
        }

        return $current;
    }

    private static function merge($targetObject, $sourceObject)
    {
        foreach (get_object_vars($sourceObject) as $propertyName => $propertyValue) {
            if (!isset($targetObject->$propertyName)) {
                $targetObject->$propertyName = $propertyValue;
            }
        }

        return $targetObject;
    }

}
