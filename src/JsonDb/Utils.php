<?php

namespace Phidias\JsonDb;

class Utils
{
    public static function bindParameters($string, $parameters = null)
    {
        if (!$parameters) {
            return $string;
        }

        $parameters = json_decode(json_encode($parameters));
        $parameterNames = [];
        $sanitizedValues = [];

        foreach ($parameters as $key => $value) {
            $parameterNames[]   = ":$key";
            $sanitizedValues[]  = self::sanitize($value);
        }

        return str_replace($parameterNames, $sanitizedValues, $string);
    }

    public static function sanitize($value)
    {
        if (is_null($value)) {
            return "NULL";
        } elseif (is_numeric($value)) {
            return $value;
        } elseif (is_string($value)) {
            if ($value == '') {
                return "''";
            }

            // Passthroug strings enclosed in backticks (must be at least 3 characters long: `x`, Otherwise values like "`"  or "``" will return an empty string)
            if (strlen($value) > 2 && $value[0] == '`' && $value[strlen($value) - 1] == '`') {
                return substr($value, 1, -1);
            }

            return "'" . self::escape($value) . "'";
        } elseif (is_bool($value)) {
            return $value ? 1 : 0;
        } elseif (is_array($value)) {
            $sanitizedValues = array();
            foreach ($value as $subvalue) {
                $sanitizedValues[] = self::sanitize($subvalue);
            }

            return '(' . implode(', ', $sanitizedValues) . ')';
        }

        return null;
    }

    public static function escape_string($string)
    {
        /**
         * Returns a string with backslashes before characters that need to be escaped.
         * As required by MySQL and suitable for multi-byte character sets
         * Characters encoded are NUL (ASCII 0), \n, \r, \, ', ", and ctrl-Z.
         *
         * @param string $string String to add slashes to
         * @return $string with `\` prepended to reserved characters
         *
         * @author Trevor Herselman
         */
        // if (function_exists('mb_ereg_replace')) {
        //     function mb_escape(string $string)
        //     {
        //         return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x27\x5C]', '\\\0', $string);
        //     }
        // } else {
        //     function mb_escape(string $string)
        //     {
        //         return preg_replace('~[\x00\x0A\x0D\x1A\x22\x27\x5C]~u', '\\\$0', $string);
        //     }
        // }

        $escaped = '';
        if (function_exists('mb_ereg_replace')) {
            $escaped = mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x27\x5C]', '\\\0', $string);
        } else {
            $escaped = preg_replace('~[\x00\x0A\x0D\x1A\x22\x27\x5C]~u', '\\\$0', $string);
        }

        return "'$escaped'";
    }

    public static function escape($string)
    {
        return "'" . self::escape_string($string) . "'";
    }


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
}
