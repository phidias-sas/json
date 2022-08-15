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

        $this->defineOperator('boolean.isTrue', [$className, 'op_true']);
        $this->defineOperator('boolean.isFalse', [$className, 'op_false']);

        $this->defineOperator('number.eq', ['\Phidias\JsonDb\Operators\OpNumber', 'eq']);
        $this->defineOperator('number.gt', ['\Phidias\JsonDb\Operators\OpNumber', 'gt']);
        $this->defineOperator('number.gte', ['\Phidias\JsonDb\Operators\OpNumber', 'gte']);
        $this->defineOperator('number.lt', ['\Phidias\JsonDb\Operators\OpNumber', 'lt']);
        $this->defineOperator('number.lte', ['\Phidias\JsonDb\Operators\OpNumber', 'lte']);
        $this->defineOperator('number.between', ['\Phidias\JsonDb\Operators\OpNumber', 'between']);

        $this->defineOperator('string.same', ['\Phidias\JsonDb\Operators\OpString', 'same']);
        $this->defineOperator('string.like', ['\Phidias\JsonDb\Operators\OpString', 'like']);
        $this->defineOperator('string.eq', ['\Phidias\JsonDb\Operators\OpString', 'eq']);
        $this->defineOperator('string.neq', ['\Phidias\JsonDb\Operators\OpString', 'neq']);
        $this->defineOperator('string.includes', ['\Phidias\JsonDb\Operators\OpString', 'includes']);
        $this->defineOperator('string.startsWith', ['\Phidias\JsonDb\Operators\OpString', 'startsWith']);
        $this->defineOperator('string.endsWith', ['\Phidias\JsonDb\Operators\OpString', 'endsWith']);
        $this->defineOperator('string.empty', ['\Phidias\JsonDb\Operators\OpString', 'empty']);
        $this->defineOperator('string.nempty', ['\Phidias\JsonDb\Operators\OpString', 'nempty']);

        $this->defineOperator('enum.any', [$className, 'enum_any']);

        // To be deprecated:
        $this->defineOperator('enum.eq', [$className, 'enum_any']);
        $this->defineOperator('enum.all', [$className, 'enum_any']);


        $this->defineOperator('array.eq', ['\Phidias\JsonDb\Operators\OpArray', 'eq']);
        $this->defineOperator('array.hasAny', ['\Phidias\JsonDb\Operators\OpArray', 'hasAny']);
        $this->defineOperator('array.hasAll', ['\Phidias\JsonDb\Operators\OpArray', 'hasAll']);

        $this->defineOperator('date.between', ['\Phidias\JsonDb\Operators\OpDate', 'between']);
        $this->defineOperator('date.eq', ['\Phidias\JsonDb\Operators\OpDate', 'eq']);
        $this->defineOperator('date.neq', ['\Phidias\JsonDb\Operators\OpDate', 'neq']);
        $this->defineOperator('date.gt', ['\Phidias\JsonDb\Operators\OpDate', 'gt']);
        $this->defineOperator('date.gte', ['\Phidias\JsonDb\Operators\OpDate', 'gte']);
        $this->defineOperator('date.lt', ['\Phidias\JsonDb\Operators\OpDate', 'lt']);
        $this->defineOperator('date.lte', ['\Phidias\JsonDb\Operators\OpDate', 'lte']);

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

        // if ($args && is_string($args) /*&& !is_numeric($args)*/ && $operatorName != 'contains' && $operatorName != 'hasAny') {
        //     $args = DbUtils::escape($args);
        // }

        return $callable($fieldName, $args, $vm);
    }


    public static function enum_any($fieldName, $args)
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


    /* Basic SQL operators */
    public static function op_true($fieldName)
    {
        return "$fieldName IN ('true', '1')";
    }

    public static function op_false($fieldName)
    {
        return "$fieldName IN ('null', 'false', '0')";
    }

    public static function op_between($fieldName, $args)
    {
        if (!is_array($args) || count($args) != 2) {
            throw new \Exception("Invalid arguments for operator 'between'");
        }

        $condition = $fieldName . " BETWEEN " . DbUtils::escape($args[0])  . " AND " . DbUtils::escape($args[1]);
        return $condition;
    }

    public static function op_eq($fieldName, $args)
    {
        if (is_array($args)) {
            return self::op_in($fieldName, $args);
        }

        $args = DbUtils::escape($args);
        return "$fieldName = $args";
    }

    public static function op_neq($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName != $args";
    }

    public static function op_gt($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName > $args";
    }

    public static function op_gte($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName >= $args";
    }

    public static function op_lt($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName < $args";
    }

    public static function op_lte($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName <= $args";
    }

    public static function op_like($fieldName, $args)
    {
        $args = DbUtils::escape($args);
        return "$fieldName LIKE $args";
    }

    public static function op_in($fieldName, $args, $vm = null)
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
