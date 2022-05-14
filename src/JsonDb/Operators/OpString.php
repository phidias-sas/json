<?php

namespace Phidias\JsonDb\Operators;

use Phidias\JsonDb\Utils as DbUtils;

class OpString
{
    public static function same($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName = $args";
    }

    public static function like($fieldName, $args)
    {
        return "$fieldName LIKE $args";
    }

    public static function eq($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName = $args";
    }

    public static function neq($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName != $args";
    }

    public static function includes($fieldName, $args)
    {
        return "$fieldName LIKE '%$args%'";
    }

    public static function startsWith($fieldName, $args)
    {
        return "$fieldName LIKE '$args%'";
    }

    public static function endsWith($fieldName, $args)
    {
        return "$fieldName LIKE '%$args'";
    }

    public static function empty($fieldName, $args)
    {
        return "$fieldName = ''";
    }

    public static function nempty($fieldName, $args)
    {
        return "$fieldName != ''";
    }

}