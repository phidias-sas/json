<?php
namespace Phidias\Json;

const PROPERTY_PREFIX = "$";

class Schema
{
    private $schema;

    public function __construct($schema)
    {
        self::hidrate($this, $schema);
    }

    public function getExample()
    {
        if (is_scalar($this->schema)) {
            return $this->schema;
        }

        if (is_array($this->schema)) {
            return $this->schema[rand(0, count($this->schema)-1)]->getExample();
        }

        $type = '$type';
        if (isset($this->schema->$type)) {
            return self::getTypeExample($this->schema->$type, $this->schema);
        }

        $sampleObject = new \stdClass;
        foreach (get_object_vars($this->schema) as $propertyName => $propertyValue) {
            if (substr($propertyName, 0, strlen(PROPERTY_PREFIX)) == PROPERTY_PREFIX) {
                continue;
            }
            $sampleObject->$propertyName = $propertyValue->getExample();
        }

        return $sampleObject;
    }

    private static function getTypeExample($type, $schema)
    {
        $type = strtolower($type);

        switch ($type) {

            case "array":
                $array  = [];
                $nItems = rand(2, 5);

                $items = '$items';
                if (isset($schema->$items)) {
                    $itemSchema = new Schema($schema->$items);
                } else {
                    $itemSchema = null;
                }

                for ($cont = 1; $cont <= $nItems; $cont++) {
                    $array[] = $itemSchema != null ? $itemSchema->getExample() : $cont;
                }

                return $array;

            case "boolean":
                return rand(0,1) == 0;

            case "integer":
                return rand(0, 9999999);

            case "string":
                return self::getRandomString();

            default:
                return "Some $type";
        }
    }

    private static function getRandomString()
    {
        //$syllables      = ["ma", "na", "ta", "gra", "sa", "la", "ba", "ge", "te", "re", "se", "de", "te", "mi", "ni", "ri", "chi", "jui", "ti", "pi", "so", "co", "po", "do", "lo", "mo", "co", "cho", "cu", "pu", "tu", "su", "lu", "chu", "ga", "ma", "na", "gra", "tra", "pra", "ba", "la", "cha", "des"];
        $syllables      = ["ma", "na", "ta", "gra", "sa", "la", "ba", "ge", "te", "re", "se", "de", "te", "mi", "ni", "ri", "sli", "jui", "ti", "pi", "so", "co", "po", "do", "lo", "mo", "co", "slo", "cu", "pu", "tu", "su", "lu", "slu", "ga", "ma", "na", "gra", "tra", "pra", "ba", "la", "sla", "des"];
        $syllablesCount = count($syllables);

        $output = '';

        $nsyllables = rand(2, 5);
        for ($cont = 1; $cont <= $nsyllables; $cont++) {
            $output .= $syllables[rand(0, $syllablesCount-1)];
        }

        return $output;
    }

    private static function hidrate(Schema $schema, $sourceObject)
    {
        if (is_object($sourceObject)) {

            $schema->schema = new \stdClass;
            foreach (get_object_vars($sourceObject) as $propertyName => $propertyValue) {

                if (substr($propertyName, 0, strlen(PROPERTY_PREFIX)) == PROPERTY_PREFIX) {
                    $schema->schema->$propertyName = $propertyValue;
                } else {
                    $schema->schema->$propertyName = new Schema($propertyValue);

                    //alias properties
                    $schema->$propertyName = &$schema->schema->$propertyName;
                }

            }

        } elseif (is_array($sourceObject)) {

            $schema->schema = [];
            foreach ($sourceObject as $key => $item) {
                $schema->schema[$key] = new Schema($item);
            }

        } else {
            $schema->schema = $sourceObject;
        }
    }



    public static function validate($subject, $schema)
    {
        dump("Validating subject", $subject, "against schema", $schema);

        if (is_scalar($schema)) {
            return $subject == $schema;
        }

        if (is_array($schema)) {
            foreach ($schema as $subschema) {
                self::validate($subject, $subschema);
            }
        }

        if (is_object($schema)) {
            return self::validateSubject($subject, $schema);
        }
    }

    public static function validateSubject($subject, $schema)
    {
        foreach (get_object_vars($schema) as $schemaProperty => $propertyValue) {

            if (substr($schemaProperty, 0, strlen(PROPERTY_PREFIX)) == PROPERTY_PREFIX) {

                $propertyName  = strtolower(substr($schemaProperty, strlen(PROPERTY_PREFIX)));

                // Now, let's look for a validator
                $dispatcherClassName = "\Phidias\Json\Validator\\".ucfirst($propertyName);
                if (class_exists($dispatcherClassName)) {
                    $dispatcherClassName::validate($subject, $propertyValue);
                }

            } else {

                // A regular property name in the schema means that the schema expects the subject to be an
                // object, and cointain this property

                // Special case: See if this property is $required
                $required = PROPERTY_PREFIX.'required';
                if (isset($propertyValue->$required)) {

                    if (!isset($subject->$schemaProperty)) {
                        dump("Subject must contain property", $schemaProperty);
                    }

                }

                if (isset($subject->$schemaProperty)) {
                    self::validate($subject->$schemaProperty, $propertyValue);
                }

            }
        }

    }


}
