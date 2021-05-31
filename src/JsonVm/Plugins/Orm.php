<?php

namespace Phidias\JsonVm\Plugins;

class Orm extends \Phidias\JsonVm\Plugins\Sql
{
    public static function install($vm)
    {
        \Phidias\JsonVm\Plugins\Sql::install($vm);

        $vm->defineStatement('deudor', [self::class, 'deudor']);
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
