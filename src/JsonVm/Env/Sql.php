<?php

namespace Phidias\Core\Vm\Env;

class Sql
{
    public static function install($vm)
    {
        $vm->defineFunction('deudores', [self::class, 'deudores']);

        $vm->defineOperator('eq', [self::class, 'eq']);
        $vm->defineOperator('neq', [self::class, 'neq']);
        $vm->defineOperator('like', [self::class, 'like']);
        $vm->defineOperator('in', [self::class, 'in']);
        $vm->defineOperator('gt', [self::class, 'gt']);
        $vm->defineOperator('gte', [self::class, 'gte']);
        $vm->defineOperator('lt', [self::class, 'lt']);
        $vm->defineOperator('lte', [self::class, 'lte']);
    }

    public static function deudores($args)
    {
        $field = 'person';
        if (isset($args->field) && $args->field == "responsible") {
            $field = $args->field;
        }

        $minValue = isset($args->debt) ? $args->debt : 1000000;
        return "id IN (SELECT $field FROM sophia_debits WHERE balance > 0 AND accounting_date > 0 AND invalidation_date IS NULL GROUP BY person HAVING SUM(balance) > $minValue)";
    }

    public static function eq($value, $args)
    {
        return "$value = $args";
    }

    public static function neq($value, $args)
    {
        return "$value != $args";
    }

    public static function like($value, $args)
    {
        return "$value LIKE $args";
    }

    public static function gt($value, $args)
    {
        return "$value > $args";
    }

    public static function gte($value, $args)
    {
        return "$value >= $args";
    }

    public static function lt($value, $args)
    {
        return "$value < $args";
    }

    public static function lte($value, $args)
    {
        return "$value <= $args";
    }

    public static function in($value, $args, $vm)
    {
        if (!is_array($args) || !count($args)) {
            return "0";
        }

        $items = [];
        foreach ($args as $arg) {
            $items[] = $vm->eval($arg);
        }

        return "$value IN (" . implode(", ", $items) . ")";
    }
}
