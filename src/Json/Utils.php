<?php

namespace Phidias\Json;

class Utils
{
    const UNCHANGED = '--UNCHANGED--';

    /*
    Given a flat associative array:
    [
        'foo' => 'Hello',
        'thing1' => 'Thing value 1',
        'thing2' => 'Thing value 2',
        'thing3' => 'Thing value 3',
        'thing4' => 'Thing value 4',
        'data' => '{"hello":"This is a JSON encoded string"}'
    ]

    and a TRANSFORMATION object
    Which is a representation of the desired output
    Property values correspond to array keys

    {
        "title": "foo",
        "things": {
            "one": "thing1",
            "two": "thing2",
            "three": "thing3",
            "four": "thing4"
        },
        "decodedData": {"$json_decode": "data"}   // objects with a single property that begin with "$" are treated as filter functions
    }

    returns an object with the desired structure, matched values, and ran through filters

    {
        "title": "Hello",
        "things": {
            "one": "Thing value 1",
            "two": "Thing value 2",
            "three": "Thing value 3",
            "four": "Thing value 4"
        },
        "decodedData": { // this is the result of json_decode($array['data'])
            "hello":"This is a JSON encoded string"
        }
    }
    */
    public static function arrayToObject($array, $object)
    {
        $result = new \stdClass;
        foreach ($object as $propName => $arrayKey) {
            if (isset($arrayKey->{'$json_decode'})) {
                $result->{$propName} = json_decode($array[$arrayKey->{'$json_decode'}]);
            } elseif (is_object($arrayKey)) {
                //  value is a nested object
                $result->{$propName} = self::arrayToObject($array, $arrayKey);
            } else {
                // otherwise, use the value as a key to retrieve value from the associative array
                $result->{$propName} = $array[$arrayKey];
            }
        }
        return $result;
    }


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
            foreach ($string as $key => $substring) {
                $retval[$key] = self::parse($substring, $sourceData, $preserveUndefined);
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
