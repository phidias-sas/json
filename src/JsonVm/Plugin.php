<?php

namespace Phidias\JsonVm;

class Plugin
{
    public static function install(Vm $vm)
    {
        throw new \Exception("Plugin did not define an ::install function");
    }    
}