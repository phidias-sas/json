<?php

namespace Phidias\JsonDb\Connector\Bigtable;

class JsonVmPlugin extends \Phidias\JsonVm\Plugin
{
    public static function install($vm)
    {
        // \Phidias\JsonVm\Plugins\Sql::install($vm);
        $vm->defineStatement('and', ['\Phidias\JsonVm\Plugins\Sql', 'stmtAnd']);
        $vm->defineStatement('or', ['\Phidias\JsonVm\Plugins\Sql', 'stmtOr']);
        $vm->defineStatement('not', ['\Phidias\JsonVm\Plugins\Sql', 'stmtNot']);

        $vm->defineStatement('op', [self::class, 'stmtOp']);

        $vm->defineStatement('deudor', [self::class, 'deudor']);
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
        $callable = ['\Phidias\JsonVm\Plugins\Sql', 'op_' . $operatorName];
        if (!is_callable($callable)) {
            throw new \Exception("Undefined operator '$operatorName'");
        }

        if (!isset($expr->field)) {
            throw new \Exception("No field specified in operator '$operatorName'");
        }

        $fieldName = $expr->field;
        if (substr($fieldName, 0, 7) == "record.") {
            $fieldName = substr($fieldName, 7);
        } else {
            $fieldName = "JSON_EXTRACT(data, '$.$fieldName')";
        }


        $args = isset($expr->args) ? $expr->args : null;
        if ($args && !is_numeric($args)) {
            $args = "'" . str_replace("'", "\'", $args) . "'";
        }

        $opResult = $callable($fieldName, $args, $vm);
        return $opResult;
    }

    public static function deudor($expr, $vm)
    {
        $deudaSettings = $expr->deudor;

        $field = 'person';
        if (isset($deudaSettings->field) && $deudaSettings->field == "responsible") {
            $field = $deudaSettings->field;
        }

        $minValue = isset($deudaSettings->debt) ? $deudaSettings->debt : 1000000;
        return "id IN (SELECT $field FROM sophia_debits WHERE balance > 0 AND accounting_date > 0 AND invalidation_date IS NULL GROUP BY person HAVING SUM(balance) > $minValue)";
    }
}
