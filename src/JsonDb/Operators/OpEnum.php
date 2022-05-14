<?php

namespace Phidias\JsonDb\Operators;

use Phidias\JsonDb\Utils as DbUtils;

class OpEnum
{
    public static function eq($fieldName, $args)
    {
        return self::any($fieldName, $args);
    }

    public static function any($fieldName, $args)
    {
        if (!is_array($args) || !count($args)) {
            return "0";
        }

        $items = [];
        foreach ($args as $arg) {
            $items[] = DbUtils::escape($arg);
        }

        return "$fieldName IN (" . implode(", ", $items) . ")";
    }

    public static function all($fieldName, $args)
    {
        return self::any($fieldName, $args);
    }
}