<?php

namespace Phidias\JsonVm\Plugins;

class Php extends \Phidias\JsonVm\Plugin
{
    public static function install($vm)
    {
        $vm->defineStatement('if', [self::class, 'stmtIf']);
        $vm->defineStatement('and', [self::class, 'stmtAnd']);
        $vm->defineStatement('or', [self::class, 'stmtOr']);
        $vm->defineStatement('switch', [self::class, 'stmtSwitch']);
        $vm->defineStatement('op', [self::class, 'stmtOp']);
    }

    /*
    {
        "if": {expr},
        "then": {expr},
        "else": {expr},
    }
    */
    public static function stmtIf($obj, $vm)
    {
        $if = $obj->if;
        $then = isset($obj->then) ? $obj->then : null;
        $else = isset($obj->else) ? $obj->else : null;

        if ($vm->eval($if)) {
            return $vm->eval($then);
        } else {
            return $vm->eval($else);
        }
    }

    /*
    {
        "and": [stmt1, stmt2, ...stmtN]
    }
    */
    public static function stmtAnd($expr, $vm)
    {
        $statements = $expr->and;
        if (!is_array($statements)) {
            return false;
        }

        $res = false;
        for ($i = 0; $i < count($statements); $i++) {
            $res = $vm->eval($statements[$i]);
            if (!$res) {
                return false;
            }
        }

        return $res;
    }

    /*
    {
        "or": [stmt1, stmt2, ...stmtN]
    }
    */
    public static function stmtOr($expr, $vm)
    {
        $statements = $expr->or;
        if (!is_array($statements)) {
            return false;
        }

        for ($i = 0; $i < count($statements); $i++) {
            $res = $vm->eval($statements[$i]);
            if ($res) {
                return $res;
            }
        }

        return false;
    }

    /*
    {
        "switch": "...expr...",
        "case": [
            {
                "value": "X",
                "do": "...exprX..."
            },
            {
                "value": "Y",
                "do": "...exprY..."
            },
            ...
        ],
        "default": "...expr..."
    }
    */
    public static function stmtSwitch($expr, $vm)
    {
        $switch = $expr->switch;
        $cases = isset($expr->case) && is_array($expr->case) ? $expr->case : [];
        $default = isset($expr->default) ? $expr->default : null;

        $value = $vm->eval($switch);
        for ($i = 0; $i < count($cases); $i++) {
            if (isset($cases[$i]->value) && $cases[$i]->value == $value) {
                return $vm->eval($cases[$i]->do);
            }
        }

        return $vm->eval($default);
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
        $fieldValue = isset($expr->field) ? $vm->getVariable($expr->field) : null;
        $args = isset($expr->args) ? $expr->args : null;

        $callable = [self::class, 'op_' . $operatorName];
        if (!is_callable($callable)) {
            throw new \Exception("Undefined operator '$operatorName'");
        }

        return $callable($fieldValue, $vm->eval($args), $vm);
    }



    public static function op_sum($arg1, $arg2, $vm)
    {
        return $arg1 + $arg2;
    }

    public static function op_multiply($arg1, $arg2, $vm)
    {
        return $arg1 * $arg2;
    }

    public static function op_gt($value, $args, $vm)
    {
        return $value > $args;
    }

    public static function op_gte($value, $args, $vm)
    {
        return $value >= $args;
    }

    public static function op_lt($value, $args, $vm)
    {
        return $value < $args;
    }

    public static function op_lte($value, $args, $vm)
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
