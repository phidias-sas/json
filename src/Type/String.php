<?php
namespace Phidias\Json\Type;

class String implements TypeInterface
{
    public static function getExample($schema)
    {
        $syllables = ["ma", "na", "ta", "gra", "sa", "la", "ba", "ge",
            "te", "re", "se", "de", "te", "mi", "ni", "ri", "sli", "jui",
            "ti", "pi", "so", "co", "po", "do", "lo", "mo", "co", "slo",
            "cu", "pu", "tu", "su", "lu", "slu", "ga", "ma", "na", "gra",
            "tra", "pra", "ba", "la", "sla", "des"];

        $syllablesCount = count($syllables);

        $output = '';
        $nsyllables = rand(2, 5);
        for ($cont = 1; $cont <= $nsyllables; $cont++) {
            $output .= $syllables[rand(0, $syllablesCount-1)];
        }

        return $output;
    }

    public static function validate($value, $schema)
    {
        return is_bool($value);
    }
}