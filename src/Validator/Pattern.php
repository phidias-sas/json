<?php
namespace Phidias\Json\Validator;

/**
 * Resolve the "$pattern" property of a Phidias Json Schema
 *
*/
class Pattern implements ValidatorInterface
{
    public static function validate($subject, $pattern)
    {
        dump("Validando que", $subject, "obedezca el patron", $pattern);
    }
}
