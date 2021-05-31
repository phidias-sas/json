<?php

namespace Phidias\Core\Vm;

class Controller
{
    public static function echo($expression, $dataModel = null)
    {
        $vm = new Vm($dataModel);
        $vm->plugin(new Plugins\Orm);

        return $vm->eval($expression);
    }
}
