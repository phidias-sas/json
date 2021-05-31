<?php

namespace Phidias\JsonVm;

class Vm
{
    private $model;
    private $statements;

    public function __construct($model = null)
    {
        $this->model = is_object($model) ? $model : new \stdClass;
        $this->statements = [];
    }

    public function addPlugin(Plugin $pluginObj)
    {
        $pluginObj::install($this);
    }

    public function defineStatement($statementChecker, callable $statementCallable, $statementName = null)
    {
        if (is_string($statementChecker)) {
            $propName = $statementChecker;
            $statementChecker = function ($expr) use ($propName) {
                return property_exists($expr, $propName);
            };

            $statementName = $propName;
        }

        if (!is_callable($statementChecker)) {
            throw new \Exception("defineStatement checker argument must be a callable");
        }

        $statement = (object)[
            "checker" => $statementChecker,
            "callable" => $statementCallable
        ];

        if ($statementName) {
            $this->statements[$statementName] = $statement;
        } else {
            $this->statements[] = $statement;
        }

        return $this;
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

        foreach ($this->statements as $statementDefinition) {
            $checkerCallable = $statementDefinition->checker;

            if ($checkerCallable($expr)) {
                $statementCallable = $statementDefinition->callable;
                return $statementCallable($expr, $this);
            }
        }

        return $this->stmtObject($expr);
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

    public function getVariable($varName)
    {
        return Utils::getProperty($this->model, $varName);
    }
}
