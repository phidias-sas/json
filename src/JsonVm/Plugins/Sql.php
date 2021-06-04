<?php

namespace Phidias\JsonVm\Plugins;

use Phidias\JsonDb\Utils as DbUtils;

class Sql extends \Phidias\JsonVm\Plugin
{
    public static function install($vm)
    {
        $vm->defineStatement('and', [self::class, 'stmtAnd']);
        $vm->defineStatement('or', [self::class, 'stmtOr']);
        $vm->defineStatement('not', [self::class, 'stmtNot']);
        $vm->defineStatement('op', [self::class, 'stmtOp']);
    }

    /*
    {
        "and": [stmt1, stmt2, ...stmtN]
    }
    */
    public static function stmtAnd($expr, $vm)
    {
        $statements = $expr->and;
        if (!is_array($statements) || !count($statements)) {
            return "FALSE";
        }

        $conditions = [];
        for ($i = 0; $i < count($statements); $i++) {
            $conditions[] = "(" . $vm->evaluate($statements[$i]) . ")";
        }

        return "(" . implode(" AND ", $conditions) . ")";
    }

    /*
    {
        "or": [stmt1, stmt2, ...stmtN]
    }
    */
    public static function stmtOr($expr, $vm)
    {
        $statements = $expr->or;
        if (!is_array($statements) || !count($statements)) {
            return "FALSE";
        }

        $conditions = [];
        for ($i = 0; $i < count($statements); $i++) {
            $conditions[] = "(" . $vm->evaluate($statements[$i]) . ")";
        }

        return "(" . implode(" OR ", $conditions) . ")";
    }

    /*
    {
        "not": "...expr..."
    }
    */
    public static function stmtNot($expr, $vm)
    {
        return "( NOT " . $vm->evaluate($expr->not) . ")";
    }

    /*
    {
        "op": gt | gte | lt | lte | eq | neq,
        "field": "PROPERTY NAME",
        "args": ...
    }
    */
    public static function stmtOp($expr, $vm)
    {
        $operatorName = $expr->op;
        $callable = [self::class, 'op_' . $operatorName];
        if (!is_callable($callable)) {
            throw new \Exception("Undefined operator '$operatorName'");
        }

        if (!isset($expr->field)) {
            throw new \Exception("No field specified in operator '$operatorName'");
        }

        $fieldName = $expr->field;
        $args = isset($expr->args) ? $expr->args : null;

        if ($args && is_string($args) && !is_numeric($args)) {
            $args = DbUtils::escape($args);
        }

        return $callable($fieldName, $args, $vm);
    }

    public static function op_eq($fieldName, $args)
    {
        return "$fieldName = $args";
    }

    public static function op_neq($fieldName, $args)
    {
        return "$fieldName != $args";
    }

    public static function op_gt($fieldName, $args)
    {
        return "$fieldName > $args";
    }

    public static function op_gte($fieldName, $args)
    {
        return "$fieldName >= $args";
    }

    public static function op_lt($fieldName, $args)
    {
        return "$fieldName < $args";
    }

    public static function op_lte($fieldName, $args)
    {
        return "$fieldName <= $args";
    }

    public static function op_like($fieldName, $args)
    {
        return "$fieldName LIKE $args";
    }

    public static function op_in($fieldName, $args, $vm)
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

}
