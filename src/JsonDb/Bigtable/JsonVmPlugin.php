<?php

namespace Phidias\JsonDb\Bigtable;

class JsonVmPlugin extends \Phidias\JsonVm\Plugin
{
    public static function install($vm)
    {
        \Phidias\JsonVm\Plugins\Sql::install($vm);
        $vm->defineStatement('op', [self::class, 'stmtOp']);
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
        if ($args && is_string($args) && !is_numeric($args)) {
            $args = \Phidias\JsonVm\Plugins\Sql::escape($args);
        }

        $opResult = $callable($fieldName, $args, $vm);
        return $opResult;
    }
}
