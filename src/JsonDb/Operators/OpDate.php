<?php

namespace Phidias\JsonDb\Operators;

class OpDate
{
    public static function between($fieldName, $args)
    {
        if (!is_array($args) || count($args) != 2) {
            return "0";
        }

        // Convert YYYY-MM-DD to timestamp:
        // UNIX_TIMESTAMP("1982-05-05 03:04:05")

        $conditions = [];
        $conditions[] = "$fieldName >= UNIX_TIMESTAMP('{$args[0]}')";
        $conditions[] = "$fieldName <= UNIX_TIMESTAMP('{$args[1]}')";

        return "(" . implode(" AND ", $conditions) . ")";
    }

    public static function eq($fieldName, $args)
    {
        $conditions = [];
        $conditions[] = "$fieldName >= UNIX_TIMESTAMP('$args 00:00:00')";
        $conditions[] = "$fieldName <= UNIX_TIMESTAMP('$args 23:59:59')";

        return "(" . implode(" AND ", $conditions) . ")";
    }

    public static function neq($fieldName, $args)
    {
        return "NOT (" . self::eq($fieldName, $args) . ")";
    }

    public static function gt($fieldName, $args)
    {
        return "$fieldName > UNIX_TIMESTAMP('$args 23:59:59')";
    }

    public static function gte($fieldName, $args)
    {
        return "$fieldName >= UNIX_TIMESTAMP('$args 00:00:00')";
    }

    public static function lt($fieldName, $args)
    {
        return "$fieldName < UNIX_TIMESTAMP('$args 00:00:00')";
    }

    public static function lte($fieldName, $args)
    {
        return "$fieldName <= UNIX_TIMESTAMP('$args 23:59:59')";
    }
}