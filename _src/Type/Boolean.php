<?php
namespace Phidias\Json\Type;

class Boolean implements TypeInterface
{
    public static function getExample($schema)
    {
        return rand(0, 1) == 0;
    }

    public static function validate($value, $schema)
    {
        return is_bool($value);
    }
}