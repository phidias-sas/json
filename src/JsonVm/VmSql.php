<?php

namespace Phidias\JsonVm;

/**
 * Returns a string with backslashes before characters that need to be escaped.
 * As required by MySQL and suitable for multi-byte character sets
 * Characters encoded are NUL (ASCII 0), \n, \r, \, ', ", and ctrl-Z.
 *
 * @param string $string String to add slashes to
 * @return $string with `\` prepended to reserved characters
 *
 * @author Trevor Herselman
 */
if (function_exists('mb_ereg_replace')) {
    function mb_escape(string $string)
    {
        return mb_ereg_replace('[\x00\x0A\x0D\x1A\x22\x27\x5C]', '\\\0', $string);
    }
} else {
    function mb_escape(string $string)
    {
        return preg_replace('~[\x00\x0A\x0D\x1A\x22\x27\x5C]~u', '\\\$0', $string);
    }
}

class VmSql
{
    public function __construct()
    {
        $this->functions = [];
        $this->operators = [];

        // std lib
        Env\Sql::install($this);
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

    public function eval($expr)
    {
        if (is_numeric($expr)) {
            return $expr;
        }

        if (is_string($expr)) {
            return "'" . mb_escape($expr) . "'";
        }

        if (!is_object($expr)) {
            return $expr;
        }

        if (isset($expr->op)) {
            $arg1 = isset($expr->arg1) ? $expr->arg1 : null;
            $arg2 = isset($expr->arg2) ? $expr->arg2 : null;

            if (isset($expr->field)) {
                $arg1 = $expr->field;
                $arg2 = isset($expr->args) ? $expr->args : null;
            }

            return $this->stmtOp($expr->op, $arg1, $arg2);
        }

        if (isset($expr->call)) {
            return $this->stmtCall($expr->call, $expr->args);
        }

        if (isset($expr->if)) {
            return $this->stmtIf($expr->if, $expr->then, $expr->else);
        }

        if (isset($expr->switch)) {
            return $this->stmtSwitch($expr->switch, $expr->case, $expr->default);
        }

        if (isset($expr->not)) {
            $res = $this->eval($expr->not);
            return "!" . $res;
        }

        if (isset($expr->or)) {
            return $this->stmtOr($expr->or);
        }

        if (isset($expr->and)) {
            return $this->stmtAnd($expr->and);
        }
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

        return "(" . $callable($arg1, $this->eval($arg2), $this) . ")";
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

        return "(" .  $callable($fnArgs, $this) . ")";
    }

    public function stmtIf($if, $then = null, $else = null)
    {
        $evBoo = $this->eval($if);
        $evThen = $this->eval($then);
        $evElse = $this->eval($else);

        return "IF($evBoo, $evThen, $evElse)";
    }

    public function stmtSwitch($switch, $cases = [], $default = null)
    {
        if (!is_array($cases)) {
            return $this->eval($default);
        }

        $value = $this->eval($switch);
        $sql = "CASE\n";
        for ($i = 0; $i < count($cases); $i++) {
            $caseValue = $cases[$i]->value;
            $caseResult = $this->eval($cases[$i]->do);

            $sql .= "\tWHEN $value = $caseValue THEN $caseResult\n";
        }

        $sql .= "END";
        return $sql;
    }

    public function stmtOr($statements = [])
    {
        if (!is_array($statements)) {
            return "FALSE";
        }

        $retval = [];
        foreach ($statements as $statement) {
            $retval[] = $this->eval($statement);
        }
        return "(" . implode(" OR ", $retval) . ")";
    }

    public function stmtAnd($statements = [])
    {
        if (!is_array($statements)) {
            return "FALSE";
        }

        $retval = [];
        foreach ($statements as $statement) {
            $retval[] = $this->eval($statement);
        }
        return "(" . implode(" AND ", $retval) . ")";
    }
}
