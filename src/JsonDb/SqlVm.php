<?php

namespace Phidias\JsonDb;

use Phidias\JsonDb\Utils as DbUtils;

class SqlVm extends \Phidias\JsonVm\Vm
{
    public $operators;
    public $translationFunction;

    public function __construct()
    {
        parent::__construct();
        $this->operators = [];

        $className = get_called_class();
        $this->defineStatement('and', [$className, 'stmtAnd']);
        $this->defineStatement('or', [$className, 'stmtOr']);
        $this->defineStatement('not', [$className, 'stmtNot']);
        $this->defineStatement('op', [$className, 'stmtOp']);

        $this->translationFunction = null;
    }

    public function setTranslationFunction($callable)
    {
        if (!is_callable($callable)) {
            throw new \Exception("setTranslationFunction: Invalid callable in setTranslationFunction");
        }

        $this->translationFunction = $callable;
    }

    public function defineOperator($operatorName, $callable)
    {
        if (!is_callable($callable)) {
            throw new \Exception("defineOperator: Invalid callable for '$operatorName'");
        }

        $this->operators[$operatorName] = $callable;
    }

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

    public static function stmtNot($expr, $vm)
    {
        return "( NOT " . $vm->evaluate($expr->not) . ")";
    }

    public static function stmtOp($expr, $vm)
    {
        $operatorName = $expr->op;
        if (!isset($expr->field)) {
            throw new \Exception("No field specified in operator '$operatorName'");
        }

        if (isset($vm->operators[$operatorName])) {
            $callable = $vm->operators[$operatorName];
        } else {
            $callable = [get_called_class(), 'op_' . $operatorName];
            if (!is_callable($callable)) {
                throw new \Exception("Undefined operator '$operatorName'");
            }
        }

        $fieldName = $expr->field;
        if ($vm->translationFunction) {
            // $fieldName = ($vm->translationFunction)($fieldName);  // NOT PHP 5 compliant
            $trnCallabale = $vm->translationFunction;
            $fieldName = $trnCallabale($fieldName);
        }

        $args = isset($expr->args) ? $expr->args : null;

        if ($args && is_string($args) /*&& !is_numeric($args)*/ && $operatorName != 'contains' && $operatorName != 'hasAny') {
            $args = DbUtils::escape($args);
        }

        return $callable($fieldName, $args, $vm);
    }


    /* Basic SQL operators */

    public static function op_between($fieldName, $args)
    {
        if (!is_array($args) || count($args) != 2) {
            throw new \Exception("Invalid arguments for operator 'bewteen'");
        }

        $condition = $fieldName . " BETWEEN " . DbUtils::escape($args[0])  . " AND " . DbUtils::escape($args[1]);
        return $condition;
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

    public static function op_contains($fieldName, $args, $vm)
    {
        $encodedArgs = "'" . json_encode($args) . "'";
        return "JSON_CONTAINS($fieldName, $encodedArgs)";
    }

    public static function op_hasAny($fieldName, $args, $vm)
    {
        $encodedArgs = "'" . json_encode($args) . "'";
        return "JSON_CONTAINS($fieldName, $encodedArgs)";
    }
}
