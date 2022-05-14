<?php

namespace Phidias\JsonDb\Operators;

use Phidias\JsonDb\Utils as DbUtils;

class OpArray
{
    public static function eq($fieldName, $args)
    {
        $encodedArgs = "'" . json_encode($args) . "'";
        return "JSON_CONTAINS($fieldName, $encodedArgs) AND JSON_CONTAINS($encodedArgs, $fieldName)";
    }

    public static function hasAny($fieldName, $args)
    {
        $conditions = [];
        foreach ($args as $arg) {
            $encodedArg = "'" . json_encode($arg) . "'";
            $conditions[] = "JSON_CONTAINS($fieldName, $encodedArg)";
        }

        return implode(" OR ", $conditions);
    }

    public static function hasAll($fieldName, $args)
    {
        $encodedArgs = "'" . json_encode($args) . "'";
        return "JSON_CONTAINS($fieldName, $encodedArgs)";
    }
}