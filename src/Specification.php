<?php
namespace Phidias\Json;

const PREFIX = "@";

class Specification
{
    private $schema;

    public function __construct($schema)
    {
        if (is_string($schema) && ($decoded = json_decode($schema))) {
            $schema = $decoded;
        }

        $this->schema = $schema;
    }

    public function getExample()
    {
        if (is_scalar($this->schema)) {
            return $this->schema;
        }

        if (is_array($this->schema)) {
            $retval = [];
            foreach ($this->schema as $key => $subject) {
                $retval[$key] = self::example($subject);
            }
            return $retval;
        }

        $objectType = $this->schema->{PREFIX."type"} ?? "object";

        switch ($objectType) {
            case "boolean":
                return rand(0, 1) == 0;

            case "integer":
                return rand(-9999, 9999);

            case "number":
                return round(rand(-9999, 9999) / rand(1, 9999), rand(1, 6));

            case "string":
                return self::getRandomString();

            case "date":
                return mktime(rand(0,23), rand(0,60), rand(0,60), rand(1,12), rand(1,31), rand(0, date('Y')));

            case "array":
                $itemSpec = $this->schema->{PREFIX."items"} ?? (object)["@type" => "string"];

                if (isset($this->schema->{PREFIX."length"})) {
                    $nItems = $this->schema->{PREFIX."length"};
                } else {
                    $minLength = $this->schema->{PREFIX."minLength"} ?? 0;
                    $maxLength = $this->schema->{PREFIX."maxLength"} ?? 15;
                    $nItems = rand($minLength, $maxLength);
                }

                $retval = [];
                for ($cont = 1; $cont <= $nItems; $cont++) {
                    $retval[] = self::example($itemSpec);
                }

                return $retval;
        }


        $exampleObject = new \stdClass;
        foreach (get_object_vars($this->schema) as $propertyName => $propertyValue) {

            if ($propertyName == "@any") {
                $randomPossibility = $this->schema->{"@any"}[rand(0, count($this->schema->{"@any"})-1)];
                $possibleExample = self::example($randomPossibility);
                if (is_object($possibleExample)) {
                    $exampleObject = (object)array_merge_recursive((array)$exampleObject, (array)self::example($randomPossibility));
                } else {
                    $exampleObject = $possibleExample;
                }
                continue;
            }

            if (substr($propertyName,0,1) == PREFIX) {
                continue;
            }
            $exampleObject->$propertyName = self::example($propertyValue);
        }

        return $exampleObject;
    }


    /* Quick static methods */
    public static function example($schema)
    {
        return (new Specification($schema))->getExample();
    }


    private static function getRandomString()
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

    public function validate($subject)
    {
        return self::validateSubject($subject, $this->schema);
    }


    private static function validateSubject($subject, $schema)
    {
    }


    public static function __validate($subject, $schema)
    {
        dump("Validating subject", $subject, "against schema", $schema);

        if (is_scalar($schema)) {
            return $subject == $schema;
        }

        if (is_array($schema)) {
            foreach ($schema as $subschema) {
                self::validate($subject, $subschema);
            }
        }

        if (is_object($schema)) {
            return self::validateSubject($subject, $schema);
        }
    }

    public static function __validateSubject($subject, $schema)
    {
        foreach (get_object_vars($schema) as $schemaProperty => $propertyValue) {
            if (substr($schemaProperty, 0, strlen(PROPERTY_PREFIX)) == PROPERTY_PREFIX) {
                $propertyName  = strtolower(substr($schemaProperty, strlen(PROPERTY_PREFIX)));

                // Now, let's look for a validator
                $dispatcherClassName = "\Phidias\Json\Validator\\".ucfirst($propertyName);
                if (class_exists($dispatcherClassName)) {
                    $dispatcherClassName::validate($subject, $propertyValue);
                }
            } else {
                // A regular property name in the schema means that the schema expects the subject to be an
                // object, and cointain this property

                // Special case: See if this property is $required
                $required = PROPERTY_PREFIX.'required';
                if (isset($propertyValue->$required)) {
                    if (!isset($subject->$schemaProperty)) {
                        dump("Subject must contain property", $schemaProperty);
                    }
                }

                if (isset($subject->$schemaProperty)) {
                    self::validate($subject->$schemaProperty, $propertyValue);
                }
            }
        }
    }
}
