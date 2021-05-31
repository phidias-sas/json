<?php

namespace Phidias\Core\Vm;

class Utils
{
    const UNCHANGED = '--UNCHANGED--';

    public static function diff($oldValue, $newValue)
    {
        $diff = self::rawDiff($oldValue, $newValue);
        return $diff == self::UNCHANGED ? null : $diff;
    }

    private static function rawDiff($oldValue, $newValue)
    {
        if (gettype($oldValue) != gettype($newValue)) {
            return $newValue;
        }

        if (is_scalar($oldValue)) {
            return $oldValue == $newValue ? self::UNCHANGED : $newValue;
        }

        if (is_object($oldValue)) {
            $hasChanges = false;
            $retval = new \stdClass;
            foreach ($newValue as $propName => $propValue) {
                if (!isset($oldValue->$propName)) {
                    $retval->$propName = $propValue;
                    $hasChanges = true;
                    continue;
                }

                $diff = self::rawDiff($oldValue->$propName, $propValue);
                if ($diff != self::UNCHANGED) {
                    $retval->$propName = $diff;
                    $hasChanges = true;
                }
            }
            return $hasChanges ? $retval : self::UNCHANGED;
        }

        if (is_array($oldValue)) {
            if (count($oldValue) != count($newValue)) {
                return $newValue;
            }

            $count = count($oldValue);
            for ($index = 0; $index < $count; $index++) {
                $diff = self::rawDiff($oldValue[$index], $newValue[$index]);
                if ($diff != self::UNCHANGED) {
                    return $newValue;
                }
            }

            return self::UNCHANGED;
        }

        return self::UNCHANGED;
    }

    public static function merge($objA, $objB)
    {
        if (!$objA || !is_object($objA)) {
            $objA = new \stdClass;
        }

        foreach ($objB as $propName => $propValue) {
            $objA->$propName = is_object($propValue) ? self::merge(isset($objA->$propName) ? $objA->$propName : null, $propValue) : $propValue;
        }

        return $objA;
    }

    // public static function getProperty($obj, $propertyName)
    // {
    //     if (isset($obj->$propertyName)) {
    //         return $obj->$propertyName;
    //     }

    //     $parts = explode(".", $propertyName, 2);
    //     if (count($parts) == 2 && isset($obj->{$parts[0]})) {
    //         return self::getProperty($obj->{$parts[0]}, $parts[1]);
    //     }

    //     return null;
    // }

    public static function getProperty($sourceObject, $propertyName)
    {
        if (!$sourceObject || !$propertyName) {
            return;
        }

        $propertyName = preg_replace("/\[(\w+)\]/", '.$1', $propertyName); // source[cosa] => source.cosa}
        $propertyName = preg_replace('/^\./', '', $propertyName); // remove trailing dot
        $o = $sourceObject;

        $a = explode(".", $propertyName);
        for ($i = 0, $n = count($a); $i < $n; $i++) {
            // Ocurre cuando  propertyName:"nombre.foo"  sourceObject: {nombre: "A string"}
            if ($o === null || !(is_object($o) || is_array($o))) {
                return;
            }

            $k = $a[$i];
            if (is_object($o) && isset($o->$k)) {
                $o = $o->$k;
            } else if (is_array($o) && isset($o[$k])) {
                $o = $o[$k];
            } else {
                return;
            }
        }

        return $o;
    }


    public static function setProperty($obj, $propertyName, $value)
    {
        $parts = explode(".", $propertyName, 2);

        if (count($parts) == 1) {
            $obj->$propertyName = $value;
            return $obj;
        }

        if (count($parts) == 2) {
            if (!isset($obj->{$parts[0]})) {
                $obj->{$parts[0]} = new \stdClass;
            }

            return self::setProperty($obj->{$parts[0]}, $parts[1], $value);
        }
    }

    public static function parse($string, $sourceData, $preserveUndefined = false)
    {
        if (!$string) {
            return $string;
        }

        if ($string == '{{}}') {
            return $sourceData;
        }

        if (is_array($string)) {
            $retval = [];
            foreach ($string as $substring) {
                $retval[] = self::parse($substring, $sourceData, $preserveUndefined);
            }
            return $retval;
        }

        if (is_object($string)) {
            $retval = [];
            foreach (get_object_vars($string) as $propertyName => $propertyValue) {
                $retval[$propertyName] = self::parse($propertyValue, $sourceData, $preserveUndefined);
            }
            return (object)$retval;
        }

        if (!is_string($string)) {
            return $string;
        }

        $newValues = [];
        $matches = [];
        preg_match_all('/{{(.+?)}}/', $string, $matches);

        if (!isset($matches[1]) || !count($matches[1])) {
            return $string;
        }

        foreach ($matches[1] as $matchIndex => $match) {
            if (!$match = trim($match)) {
                continue;
            }

            $originalString = $matches[0][$matchIndex];

            $targetValue = self::getProperty($sourceData, $match);
            if ($targetValue === null) {
                if ($preserveUndefined) {
                    continue;
                }
                $targetValue = '';
            }

            if ($string == $originalString) {
                return $targetValue;
            }

            if (!is_string($targetValue)) {
                $targetValue = json_encode($targetValue/*, JSON_PRETTY_PRINT*/);
            }

            $newValues[$originalString] = $targetValue;
        }

        return str_replace(array_keys($newValues), $newValues, $string);
    }
}
