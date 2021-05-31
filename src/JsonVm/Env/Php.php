<?php

namespace Phidias\JsonVm\Env;

class Php extends \Phidias\JsonVm\Plugin
{
    public static function install($vm)
    {
        $vm->defineFunction('echo', [self::class, 'echo']);
        $vm->defineFunction('stuff', [self::class, 'stuff']);

        $vm->defineOperator('gt', [self::class, 'gt']);
        $vm->defineOperator('lt', [self::class, 'lt']);
        $vm->defineOperator('gte', [self::class, 'gte']);
        $vm->defineOperator('lte', [self::class, 'lte']);
        $vm->defineOperator('mult', [self::class, 'mult']);

        $vm->defineOperator('array.map', [self::class, 'arrayMap']);
        $vm->defineOperator('array.filter', [self::class, 'arrayFilter']);
    }

    public static function echo($args)
    {
        return $args;
    }

    public static function stuff($args)
    {
        return (object)[
            "stuff" => $args,
            "hello" => "World!"
        ];
    }

    public static function mult($arg1, $arg2)
    {
        return $arg1 * $arg2;
    }

    public static function gt($value, $args)
    {
        return $value > $args;
    }

    public static function gte($value, $args)
    {
        return $value >= $args;
    }

    public static function lt($value, $args)
    {
        return $value < $args;
    }

    public static function lte($value, $args)
    {
        return $value <= $args;
    }

    public static function arrayMap($value, $args, $vm)
    {
        if (!is_array($value)) {
            return [];
        }

        $retval = [];
        foreach ($value as $i => $item) {
            $retval[$i] = $vm->runClosure($args, [$item, $i]);
        }
        return $retval;
    }

    public static function arrayFilter($value, $args, $vm)
    {
        if (!is_array($value)) {
            return [];
        }

        $retval = [];
        foreach ($value as $i => $item) {
            $result = $vm->runClosure($args, [$item, $i]);
            if ($result) {
                $retval[] = $item;
            }
        }
        return $retval;
    }
}
