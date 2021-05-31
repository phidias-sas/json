<?php

namespace Phidias\JsonVm\Plugins;

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
            $conditions[] = "(" . $vm->eval($statements[$i]) . ")";
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
            $conditions[] = "(" . $vm->eval($statements[$i]) . ")";
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
        return "( NOT " . $vm->eval($expr->not) . ")";
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
            $args = self::escape($args);
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
            $items[] = self::escape($arg);
        }

        return "$fieldName IN (" . implode(", ", $items) . ")";
    }

    public static function escape($string)
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
}
