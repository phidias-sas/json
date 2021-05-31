<?php
namespace Phidias\Json\Validator;

/*
 Resolve the "$type" property of a Phidias Json Schema

{
    "$type": "type",
}


*/
class Type implements ValidatorInterface
{
    public static function validate($subject, $type)
    {
        dump("Validando que", $subject, "sea de tipo", $type);
        
        switch ($type) {
            case "string":
                return is_string($subject);
            break;

            case "integer":
                return is_integer($subject);
            break;

            case "numeric":
                return is_numeric($subject);
            break;

            case "array":
                return is_array($subject);
            break;
        }
    }
}
