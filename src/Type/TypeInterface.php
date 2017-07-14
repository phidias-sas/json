<?php
namespace Phidias\Json\Type;

interface TypeInterface
{
    public static function getExample($schema);
    public static function validate($value, $schema);
}
