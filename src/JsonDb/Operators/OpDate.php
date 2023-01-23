<?php

namespace Phidias\JsonDb\Operators;

class OpDate
{
    private static function sanitizeFieldName($fieldName)
    {
        return "IF(UNIX_TIMESTAMP($fieldName), UNIX_TIMESTAMP($fieldName), $fieldName)";
    }

    public static function between($fieldName, $args)
    {
        if (!is_array($args) || count($args) != 2) {
            return "0";
        }

        $fieldName = self::sanitizeFieldName($fieldName);

        // Convert YYYY-MM-DD to timestamp:
        // UNIX_TIMESTAMP("1982-05-05 03:04:05")

        $conditions = [];
        $conditions[] = "$fieldName >= UNIX_TIMESTAMP('{$args[0]} 00:00:00')";
        $conditions[] = "$fieldName <= UNIX_TIMESTAMP('{$args[1]} 23:59:59')";

        return "(" . implode(" AND ", $conditions) . ")";
    }

    public static function eq($fieldName, $args)
    {
        $fieldName = self::sanitizeFieldName($fieldName);

        $conditions = [];
        $conditions[] = "$fieldName >= UNIX_TIMESTAMP('$args 00:00:00')";
        $conditions[] = "$fieldName <= UNIX_TIMESTAMP('$args 23:59:59')";

        return "(" . implode(" AND ", $conditions) . ")";
    }

    public static function neq($fieldName, $args)
    {
        $fieldName = self::sanitizeFieldName($fieldName);
        return "NOT (" . self::eq($fieldName, $args) . ")";
    }

    public static function gt($fieldName, $args)
    {
        $fieldName = self::sanitizeFieldName($fieldName);
        return "$fieldName > UNIX_TIMESTAMP('$args 23:59:59')";
    }

    public static function gte($fieldName, $args)
    {
        $fieldName = self::sanitizeFieldName($fieldName);
        return "$fieldName >= UNIX_TIMESTAMP('$args 00:00:00')";
    }

    public static function lt($fieldName, $args)
    {
        $fieldName = self::sanitizeFieldName($fieldName);
        return "$fieldName < UNIX_TIMESTAMP('$args 00:00:00')";
    }

    public static function lte($fieldName, $args)
    {
        $fieldName = self::sanitizeFieldName($fieldName);
        return "$fieldName <= UNIX_TIMESTAMP('$args 23:59:59')";
    }
}
