# Phidias JSON Annotations

Phidias JSON Annotations are a series of JSON properties (prefixed with "@")
that describe its schema and behavior


Take this JSON object for example:
```
{
    "id": "123",
    "firstName": "Santiago",
    "lastName": "Cortes",
    "birthday": "01/15/1982"
}
```

And its annotated example
```
{
    "@title": "Person Object",
    "@type": "object",

    "id": {
        "@title": "Unique identifier",
        "@type": "string",
        "@pattern": "^[0-9]+",
        "@required": true
    },

    "firstName": {
        "@title": "First name",
        "@type": "string",
        "@pattern": "^[0-9]+",
        "@required": true
    },

    "lastName": {
        "@title": "Last Name",
        "@type": "string",
        "@required": true,

        "@any": [
            {
                "@pattern": "[^0-9]",
                "@message": "Last name may not contain numbers"
            },

            {
                "@pattern": "[a-zA-Z'-]",
                "@message": "Invalid characters detected"
            },

            {
                "@pattern": "^[A-Z]",
                "@message": "Last name must begin with capital letter"
            }            
        ]
    },

    "birthday": {
        "@title": "Date of birth",
        "@type": "date"
    },

    "something": {
        "@any": [
            {
                "@type": "string",
                "@pattern": "A(.)+",
                "@message": "Something strings must begin with A"
            },

            {
                "@type": "number"
            }
        ]
    }
}
```


#### @title
#### @description
#### @type
#### @required
#### @pattern
#### @length
#### @minLength
#### @maxLength

#### @ref
#### @any




### Using the PHP Class:

use Phidias\JSON\Specification;

$spec = new Specification($anAnnotatedJSONObject);

$example = $spec->getExample();

try {
    $spec->validate($someObject);
} catch (Phidias\Json\Validation\Exception $e) {
    foreach ($e->validationErrors as $error) {

    }
}


### Every JSON object is a valid Phidias JSON Schema


`"hello"`
Validates against the string "hello".

`123`
Validates against the integer 123.

```
{
    "someProperty": "Hello"
}
```
Validates that the object MAY contain "someProperty" and its value MUST be "Hello"



Arrays, however, are interpreted as array of valid schemas:

[1, 2, 3]
Validates 1, 2 or 3

Equivalent to:

{
  "$in": [1, 2, 3]
}



Properties prefixed with "$" define special validation rules:


$type
Validates that the given object is of a particular type

{
    "$type": "string"
}

Validates a string


Other types:

{"$type": "integer"}
{"$type": "array"}
{"$type": "object"}
{"$type": "boolean"}


$pattern
Match the object against the given pattern:

{
    "$type": "string",
    "$pattern": "[aA].+"
}


{
    "$type": "string",
    "$in": ["hello", "hi", "hola"]
}


{
    "gender": {"$in": ["m", "f"]},

    "firstName": {
        "$type": "string",
        "$required": true
    },

    "lastName": {
        "$type": "string",
        "$required": true
    },


    "$any": [
        {
            "$message": "All firstNames of -m- gender must end with -o-",
            "gender": "m",
            "firstName": {"$pattern": ".o"}
        },

        {
            "$message": "All firstNames of -f- gender must end with -a-",
            "gender": "f",
            "firstName": {"$pattern": ".a"}
        },

        {
            "firstName": "pat"
        }
    ]

}




Reference local or foreign documents

 * $ref


Meta-matching

 * $or
 * $any (alias to $or)
 * $and
 * $all (alias to $and)
 * $not
 * $nor
 * $xor


Basic validations

 * $type
 * $value
 * $pattern
 * $anyOf
 * $allOf
 * $oneOf
 * $length


Aplica unicamente cuando $type = integer:

 * $minimum
 * $minimumInclusive
 * $maximum
 * $maximumInclusive


Aplica unicamente cuando $type = array:

 * $items  contiene el esquema con el que se validan todos los items del arreglo


Aplica unicamente cuando $type = object:

 * $properties  contiene el esquema con el que se validan todas las propiedades del objeto


Operadores mongo:

 * $eq    Matches values that are equal to a specified value.
 * $gt    Matches values that are greater than a specified value.
 * $gte   Matches values that are greater than or equal to a specified value.
 * $lt    Matches values that are less than a specified value.
 * $lte   Matches values that are less than or equal to a specified value.
 * $ne    Matches all values that are not equal to a specified value.
 * $in    Matches any of the values specified in an array.
 * $nin   Matches none of the values specified in an array.


Custom properties
e.g.

 * $title
 * $description









This way, every object becomes its own schema:

For example, take this very random JSON object:


{
    "documentType": "TI",
    "document": 123,
    "firstName": "Santiago",
    "lastName": "Cortes",
    "birthday": 123718974,
    "relatives": {
        "mother": "",
        "father": ""
    },
    "options": [1, 2, 3]
}



Now look at it "schematized":



{
    "$title": "Una persona",
    "$description": "Esquema que define una persona",
    "documentType": {"$oneOf": ["TI", "CC", "PP"]},
    "firstName": {
        "$type": "string",
        "$required": true,
        "$pattern": "[a-zA-Z]+"
    },
    "lastName": {
        "$type": "string",
        "$required": true,
        "$pattern": "[a-zA-Z]+"
    },

    "$any": [

        {

            "$description": "cuando documentType = TI, document es obligatorio"
            "documentType": "TI",
            "document": {
                "$type": "string",
                "$required": true,
                "$pattern": "[a-zA-Z]+"
            }
        },

        {

            "$description": "cuando documentType = CC, document debe empezar por A"
            "documentType": "CC",

            "document": {
                "$type": "string",
                "$required": true,
                "$pattern": "a[a-zA-Z]+"
            }

        }
    ],


    "birthday": {"$type": "date"},

    "relatives": {
        "mother": {
            "$any": [
                {"$type": "string"},
                {"$type": "object"}
            ]
        },

        "father": {
            "$or": [
                {"$type": "string"},
                {"$type": "object"}
            ]
        }
    },

    "options": {
        "$type": "array",
        "$items": {"$type": "integer"}
    }
}



Este principio aplicado a un RESOURCE:



{
    "url": "people/{personId}",

    "attributes": {
        "personId": {
            "$title": "El ID de la persona",
            "$type": "string"
        }
    },

    "exchanges": [

        {
            "request": {
                "method": "get"
            }

            "response": {"$ref": "#/definitions/responses/person"}
        },

        {
            "request": {
                "method": "put",
                "body": {"$ref": "#/definitions/schemas/person"}
            },

            "response": {"$ref": "#/definitions/responses/person"}
        },

        {
            "request": {
                "method": "delete"
            }

            "response": {"$ref": "#/definitions/responses/person"}
        }

    ],


    "definitions": {

        "responses": {

            "person": {
                "$title": "Una persona",
                "$description": "Representacion de una persona",

                "$any": [
                    {
                        "headers": {"content-type": "application/json"},
                        "body": {"$ref": "http://schema.api.phidias.io/entities/person.json"}
                    },

                    {
                        "headers": {"content-type": "text/html"},
                        "body": "<div class=\"person\">...</div>"
                    }
                ]
            }

        }

    }


}


$schema = Schema::load("that/resource.json");

$thisHttpInteractionSchema = $schema->getProperty("methods")->getProperty("get");


$headers = $request->getHeaders();
$body = $request->getBody();

$thisHttpInteractionSchema->getProperty("headers")->validates($headers);
$thisHttpInteractionSchema->getProperty("body")->validates($body);




path/to/schema.json:

{
    "name": {
        "$type": "string",
        "$pattern": "a.+",
        "$required": true,
        "$message": "el nombre debe empezar por A"
    },

    "age": {
        "$type": "integer",
        "$required": true,
        "$message": "debes incluir una edad"
    },

    "foo": {
        "$in": [
            {
                "$type": "string",
                "$pattern": "a.+",
                "$message": "foo debe empezar por a"
            },

            {
                "$type": "integer",
                "$minimum": "100",
                "$message": "debe ser mayor que 100"
            }
        ],

        "$message": "o un numero mayor que 100, o una cadena que empieza por a"
    }
}


$schema = Schema::load("path/to/schema.json");

$object = {
    "name": "Santiago",
    "foo": "hola"
}


try {
    $schema->validate($object);
} catch (Schema\InvalidStuff $e) {

    $errors = $e->getErrors();

    foreach ($errors as $error) {
        echo $error->getMessage();
    }

}
