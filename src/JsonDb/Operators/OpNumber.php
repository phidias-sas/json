<?php

namespace Phidias\JsonDb\Operators;

use Phidias\JsonDb\Utils as DbUtils;

class OpNumber
{
    private static function sanitizeField($fieldName)
    {
        return "CAST($fieldName as signed)";
    }

    public static function eq($fieldName, $args)
    {
        $fieldName = self::sanitizeField($fieldName);
        $args = DbUtils::escape($args);

        return "$fieldName = $args";
    }

    public static function gt($fieldName, $args)
    {
        $fieldName = self::sanitizeField($fieldName);
        $args = DbUtils::escape($args);

        return "$fieldName > $args";
    }

    public static function gte($fieldName, $args)
    {
        $fieldName = self::sanitizeField($fieldName);
        $args = DbUtils::escape($args);

        return "$fieldName >= $args";
    }

    public static function lt($fieldName, $args)
    {
        $fieldName = self::sanitizeField($fieldName);
        $args = DbUtils::escape($args);

        return "$fieldName < $args";
    }

    public static function lte($fieldName, $args)
    {
        $fieldName = self::sanitizeField($fieldName);
        $args = DbUtils::escape($args);

        return "$fieldName <= $args";
    }

    public static function between($fieldName, $args)
    {
        if (!is_array($args) || count($args) != 2) {
            return "0";
        }
        $fieldName = self::sanitizeField($fieldName);
        return $fieldName . " BETWEEN " . DbUtils::escape($args[0])  . " AND " . DbUtils::escape($args[1]);
    }
}
