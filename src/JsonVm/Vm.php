<?php

namespace Phidias\JsonVm;

class Vm
{
    private $model;

    public function __construct($model = null)
    {
        $this->functions = [];
        $this->operators = [];
        $this->model = is_object($model) ? $model : new \stdClass;

        // std libraries
        Env\Php::install($this);
    }

    public function plugin(Plugin $pluginObj)
    {
        $pluginObj::install($this);
    }

    public function defineFunction($fnName, $callable)
    {
        if (!is_callable($callable)) {
            throw new \Exception("Invalid function callable for '$fnName'");
        }

        if (isset($this->functions[$fnName])) {
            throw new \Exception("Function '$fnName' already defined");
        }

        $this->functions[$fnName] = $callable;
        return $this;
    }

    public function defineOperator($opName, $callable)
    {
        if (!is_callable($callable)) {
            throw new \Exception("Invalid operator callable from '$opName'");
        }

        if (isset($this->operators[$opName])) {
            throw new \Exception("Operator '$opName' already defined");
        }

        $this->operators[$opName] = $callable;
        return $this;
    }

    public function runClosure($closure, $arrArgs = [])
    {
        if (!isset($closure->function)) {
            throw new \Exception("Invalid close declaration");
        }

        if (!$arrArgs) {
            $arrArgs = [];
        }

        $innerModel = new \stdClass;
        $expectedArguments = isset($closure->arguments) && is_array($closure->arguments) ? $closure->arguments : [];
        foreach ($expectedArguments as $i => $expectedArgName) {
            $innerModel->$expectedArgName = isset($arrArgs[$i]) ? $arrArgs[$i] : null;
        }

        return $this->eval($closure->function, $innerModel);
    }

    public function eval($expr, $model = null)
    {
        if (!$expr || (!is_string($expr) && !is_object($expr))) {
            return $expr;
        }

        if ($model) {
            $this->model = Utils::merge($this->model, $model);
        }

        if (is_string($expr)) {
            return Utils::parse($expr, $this->model);
        }

        if (is_array($expr)) {
            $retval = [];
            for ($i = 0; $i < count($expr); $i++) {
                $retval[$i] = $this->eval($expr[$i]);
            }

            return $retval;
        }

        if (isset($expr->function)) {
            return $expr;
        }

        if (isset($expr->call)) {
            return $this->stmtCall($expr->call, $expr->args);
        }

        if (isset($expr->do)) {
            return $this->stmtDo($expr->do, $expr->assign, $expr->then);
        }

        if (isset($expr->chain)) {
            return $this->stmtChain($expr->chain);
        }

        if (isset($expr->op)) {
            $arg1 = isset($expr->arg1) ? $expr->arg1 : null;
            $arg2 = isset($expr->arg2) ? $expr->arg2 : null;

            if (isset($expr->field)) {
                $arg1 = "{{" . $expr->field . "}}";
                $arg2 = isset($expr->args) ? $expr->args : null;
            }

            return $this->stmtOp($expr->op, $arg1, $arg2);
        }

        if (isset($expr->if)) {
            return $this->stmtIf($expr->if, $expr->then, $expr->else);
        }

        if (isset($expr->switch)) {
            return $this->stmtSwitch($expr->switch, $expr->case, $expr->default);
        }

        if (isset($expr->not)) {
            return !$this->eval($expr->not);
        }

        if (isset($expr->or)) {
            return $this->stmtOr($expr->or);
        }

        if (isset($expr->and)) {
            return $this->stmtAnd($expr->and);
        }

        return $this->stmtObject($expr);
    }

    public function stmtCall($fnName, $fnArgs)
    {
        if (!isset($this->functions[$fnName])) {
            throw new Exception("Function '$fnName' is not defined");
        }

        $callable = $this->functions[$fnName];
        if (!is_callable($callable)) {
            throw new Exception("Function '$fnName' is not callable");
        }

        return $callable($this->eval($fnArgs), $this);
    }

    public function stmtDo($do, $assign = null, $then = null)
    {
        $res = $this->eval($do);
        if ($assign) {
            Utils::setProperty($this->model, $assign, $res);
        }

        return $then ? $this->eval($then) : $res;
    }

    public function stmtChain($chain)
    {
        if (!is_array($chain)) {
            return;
        }

        $res = null;

        for ($i = 0; $i < count($chain); $i++) {
            $expr = $chain[$i];
            if (!isset($expr->do)) {
                continue;
            }

            $res = $this->eval($expr->do);
            if (isset($expr->assign) && $expr->assign) {
                Utils::setProperty($this->model, $expr->assign, $res);
            }
        }

        return $res;
    }

    public function stmtOp($opName, $arg1 = null, $arg2 = null)
    {
        if (!isset($this->operators[$opName])) {
            throw new Exception("Operator '$opName' is not defined");
        }

        $callable = $this->operators[$opName];
        if (!is_callable($callable)) {
            throw new Exception("Operator '$opName' is not callable");
        }

        return $callable($this->eval($arg1), $this->eval($arg2), $this);
    }

    public function stmtIf($if, $then = null, $else = null)
    {
        $boo = $this->eval($if);
        return $boo ? $this->eval($then) : $this->eval($else);
    }

    public function stmtSwitch($switch, $cases = [], $default = null)
    {
        if (!is_array($cases)) {
            return $this->eval($default);
        }

        $value = $this->eval($switch);
        for ($i = 0; $i < count($cases); $i++) {
            if (isset($cases[$i]->value) && $cases[$i]->value == $value) {
                return $this->eval($cases[$i]->do);
            }
        }

        return $this->eval($default);
    }

    public function stmtOr($statements = [])
    {
        if (!is_array($statements)) {
            return false;
        }

        for ($i = 0; $i < count($statements); $i++) {
            $res = $this->eval($statements[$i]);
            if ($res) {
                return $res;
            }
        }

        return false;
    }

    public function stmtAnd($statements = [])
    {
        if (!is_array($statements)) {
            return false;
        }

        $res = false;
        for ($i = 0; $i < count($statements); $i++) {
            $res = $this->eval($statements[$i]);
            if (!$res) {
                return false;
            }
        }

        return $res;
    }

    public function stmtObject($obj)
    {
        if (!is_object($obj)) {
            return $obj;
        }

        $retval = new \stdClass;
        foreach (get_object_vars($obj) as $propertyName => $propertyValue) {
            $retval->$propertyName = $this->eval($propertyValue);
        }
        return $retval;
    }
}
