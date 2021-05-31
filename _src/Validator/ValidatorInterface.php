<?php
namespace Phidias\Json\Validator;

interface ValidatorInterface
{
    public static function validate($value, $validatedObject);
}
