<?php

namespace Phidias\Json\Sql;

use Phidias\JsonDb\Utils as DbUtils;

class Vm extends \Phidias\Json\Vm
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
        $this->defineStatement('search', [$className, 'stmtSearch']);

        $this->defineOperator('boolean.isTrue', [$className, 'op_true']);
        $this->defineOperator('boolean.isFalse', [$className, 'op_false']);

        $this->defineOperator('number.eq', ['\Phidias\Json\Sql\Operators\OpNumber', 'eq']);
        $this->defineOperator('number.gt', ['\Phidias\Json\Sql\Operators\OpNumber', 'gt']);
        $this->defineOperator('number.gte', ['\Phidias\Json\Sql\Operators\OpNumber', 'gte']);
        $this->defineOperator('number.lt', ['\Phidias\Json\Sql\Operators\OpNumber', 'lt']);
        $this->defineOperator('number.lte', ['\Phidias\Json\Sql\Operators\OpNumber', 'lte']);
        $this->defineOperator('number.between', ['\Phidias\Json\Sql\Operators\OpNumber', 'between']);

        $this->defineOperator('string.same', ['\Phidias\Json\Sql\Operators\OpString', 'same']);
        $this->defineOperator('string.like', ['\Phidias\Json\Sql\Operators\OpString', 'like']);
        $this->defineOperator('string.eq', ['\Phidias\Json\Sql\Operators\OpString', 'eq']);
        $this->defineOperator('string.neq', ['\Phidias\Json\Sql\Operators\OpString', 'neq']);
        $this->defineOperator('string.includes', ['\Phidias\Json\Sql\Operators\OpString', 'includes']);
        $this->defineOperator('string.startsWith', ['\Phidias\Json\Sql\Operators\OpString', 'startsWith']);
        $this->defineOperator('string.endsWith', ['\Phidias\Json\Sql\Operators\OpString', 'endsWith']);
        $this->defineOperator('string.empty', ['\Phidias\Json\Sql\Operators\OpString', 'empty']);
        $this->defineOperator('string.nempty', ['\Phidias\Json\Sql\Operators\OpString', 'nempty']);

        $this->defineOperator('enum.any', [$className, 'enum_any']);

        // To be deprecated:
        $this->defineOperator('enum.eq', [$className, 'enum_any']);
        $this->defineOperator('enum.all', [$className, 'enum_any']);


        $this->defineOperator('array.eq', ['\Phidias\Json\Sql\Operators\OpArray', 'eq']);
        $this->defineOperator('array.hasAny', ['\Phidias\Json\Sql\Operators\OpArray', 'hasAny']);
        $this->defineOperator('array.hasAll', ['\Phidias\Json\Sql\Operators\OpArray', 'hasAll']);

        $this->defineOperator('date.between', ['\Phidias\Json\Sql\Operators\OpDate', 'between']);
        $this->defineOperator('date.eq', ['\Phidias\Json\Sql\Operators\OpDate', 'eq']);
        $this->defineOperator('date.neq', ['\Phidias\Json\Sql\Operators\OpDate', 'neq']);
        $this->defineOperator('date.gt', ['\Phidias\Json\Sql\Operators\OpDate', 'gt']);
        $this->defineOperator('date.gte', ['\Phidias\Json\Sql\Operators\OpDate', 'gte']);
        $this->defineOperator('date.lt', ['\Phidias\Json\Sql\Operators\OpDate', 'lt']);
        $this->defineOperator('date.lte', ['\Phidias\Json\Sql\Operators\OpDate', 'lte']);

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


    public static function stmtSearch($expr, $vm)
    {
        if (!isset($expr->search->fields)) {
            return "0";
        }

        $searchString = isset($expr->search->string)
            ? trim($expr->search->string)
            : '';

        if (!$searchString) {
            return "0";
        }

        $fields = is_array($expr->search->fields)
            ? $expr->search->fields
            : ($expr->search->fields ? [$expr->search->fields] : []);

        $sanitizedTargetFields = [];
        foreach ($fields as $fieldName) {
            if ($vm->translationFunction) {
                $trnCallabale = $vm->translationFunction;
                $fieldName = $trnCallabale($fieldName);
            }
            $sanitizedTargetFields[] = "COALESCE($fieldName, '')";
        }
        $searchTargetField = count($sanitizedTargetFields) > 1
            ? "CONCAT(" . implode(", ' ', ", $sanitizedTargetFields) . ")"
            : $sanitizedTargetFields[0];

        $wordConditions = [];
        // No partir por espacios en cadenas entre comillas
        $words = preg_split('/("[^"]*")|\h+/', $searchString, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);

        foreach ($words as $word) {
            if (!$word = trim($word)) {
                continue;
            }

            if (substr($word, 0, 1) == '"') {
                $word = substr($word, 1, -1);
            }

            $word = str_replace('%', '\%', $word);

            $wordConditions[] = "$searchTargetField LIKE " . DbUtils::escape('%' . $word . '%');
        }

        return implode(" AND ", $wordConditions);
    }

    public static function enum_any($fieldName, $args)
    {
        if (!is_array($args) || !count($args)) {
            return "0";
        }

        $comparisons = [];
        foreach ($args as $arg) {
            $comparisons[] = $arg === null
                ? "$fieldName IS NULL"
                : $fieldName . ' = ' . DbUtils::escape($arg);
        }

        return "(" . implode(" OR ", $comparisons) . ")";
    }


    /* Basic SQL operators */
    public static function op_true($fieldName)
    {
        // return "$fieldName IN (true, 'true', '1')";
        return "($fieldName)";
    }

    public static function op_false($fieldName)
    {
        // return "$fieldName IN (false, 'false', 'null', '0')";
        return "NOT ($fieldName)";
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
