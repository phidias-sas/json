<?php

namespace Phidias\Json\Sql;

use Phidias\Json\Utils;

class Select
{
    /*
    Given a JSON representation of a SQL QUERY:
    {
        "select": [
            "record.id",
            "record.data",
            {"value": "person.id", "as": "person.id"},
            {"value": "person.firstname", "as": "person.firstname"},
            {"value": "person.lastname", "as": "person.lastname"},
            {"value": "person.gender", "as": "person.gender"},
            {"value": "responsible.id", "as": "responsible.id"},
            {"value": "responsible.firstname", "as": "responsible.firstname"},
            {"value": "responsible.lastname", "as": "responsible.lastname"},
            {"value": "responsible.gender", "as": "responsible.gender"}
        ],

        "from": {
            "table": "sophia_jsondb_bigtable_records",
            "as": "record"
        },

        "join": [
            {
            "type": "INNER JOIN",
            "table": "sophia_people",
            "as": "person",
            "on": "person.id = JSON_UNQUOTE(JSON_EXTRACT(record.data, '$.personId'))"
            },
            {
            "type": "INNER JOIN",
            "table": "sophia_people",
            "as": "responsible",
            "on": "responsible.id = JSON_UNQUOTE(JSON_EXTRACT(record.data, '$.responsibleId'))"
            }
        ]
    }

    returns a string with a valid SQL QUERY string:

    SELECT
    record.id,
    record.data,
    person.id as `person.id`,
    person.firstname as `person.firstname`,
    person.lastname as `person.lastname`,
    person.gender as `person.gender`,
    responsible.id as `responsible.id`,
    responsible.firstname as `responsible.firstname`,
    responsible.lastname as `responsible.lastname`,
    responsible.gender as `responsible.gender`
    FROM sophia_jsondb_bigtable_records record
    INNER JOIN sophia_people person ON person.id = JSON_UNQUOTE(JSON_EXTRACT(record.data, '$.personId'))
    INNER JOIN sophia_people responsible ON responsible.id = JSON_UNQUOTE(JSON_EXTRACT(record.data, '$.responsibleId'))
    */
    public static function toString($query)
    {
        if (!$query) {
            return "";
        }

        if (is_string($query)) {
            return $query;
        }

        if (is_array($query)) {
            $query = json_decode(json_encode($query));
        }

        $selectColumns = [];
        if (is_object($query->select)) { // determine columns when SELECT is an object
            $selectColumns = self::getSelectColumns($query->select);
        } else if (is_array($query->select)) {
            $selectColumns = $query->select;
        } else if (is_string($query->select)) {
            $selectColumns = [$query->select];
        }

        $select = "";
        foreach ($selectColumns as $selectItem) {
            if (is_string($selectItem)) {
                $column = self::sanitizeFieldName($selectItem);
                $select .= "{$column}, ";
            } else if (isset($selectItem->value)) {
                $column = self::sanitizeFieldName($selectItem->value);
                $alias = isset($selectItem->as) ? $selectItem->as : $column;
                $select .= "{$column} as `{$alias}`, ";
            }
        }
        $select = rtrim($select, ", ");

        $from = "";
        if (is_string($query->from)) {
            $from = $query->from;
        } else if (isset($query->from->table)) {
            $from = $query->from->table;
            if (isset($query->from->as)) {
                $from .= " as {$query->from->as}";
            }
        }

        $join = "";
        if (isset($query->join) && is_array($query->join)) {
            foreach ($query->join as $joinItem) {
                $joinType = $joinItem->type;
                $joinTable = $joinItem->table;
                $joinAs = $joinItem->as;
                $joinOn = self::replaceJsonArrowOperator($joinItem->on);
                $join .= "{$joinType} {$joinTable} as {$joinAs} ON {$joinOn} ";
            }
            $join = rtrim($join);
        }

        $where = "";
        if (isset($query->where)) {
            $vm = new Vm();
            $vm->setTranslationFunction(['Phidias\Json\Sql\Select', 'sanitizeFieldName']);
            $incomingWhere = $vm->evaluate($query->where);
            if ($incomingWhere) {
                $where = "WHERE ($incomingWhere)";
            }
        }

        $order = "";
        if (isset($query->order) || isset($query->orderBy)) {
            $queryOrder = isset($query->order) ? $query->order : $query->orderBy;

            $order = "ORDER BY ";
            if (is_array($queryOrder)) {
                foreach ($queryOrder as $orderItem) {
                    $column = self::sanitizeFieldName($orderItem->field);
                    $direction = isset($orderItem->desc) && $orderItem->desc ? "DESC" : "ASC";
                    $order .= "{$column} {$direction}, ";
                }
                $order = rtrim($order, ", ");
            } else if (is_string($queryOrder)) {
                $order .= $queryOrder;
            }
        }

        $limit = "";
        if (isset($query->limit)) {
            $limit = "LIMIT {$query->limit}";
        }

        return "SELECT {$select} FROM {$from} {$join} {$where} {$order} {$limit}";
    }


    public static function rowToObject($arrRow, $jsonSelect = null)
    {
        if (!$jsonSelect) {
            return (object)$arrRow;
        }

        if (!is_object($jsonSelect)) {
            $jsonSelect = json_decode(json_encode($jsonSelect));
        }

        if (is_array($jsonSelect->select) || is_string($jsonSelect->select)) {
            // select is flat a column array or specific string.  $arrRow has every returned column as a key.
            return (object)$arrRow;
        }

        $targetObjecStructure = self::toTransformationObject($jsonSelect->select);
        return Utils::arrayToObject($arrRow, $targetObjecStructure);
    }



    public static function sanitizeFieldName($fieldName)
    {
        return self::replaceJsonArrowOperator($fieldName);
    }

    private static function replaceJsonArrowOperator($query)
    {
        $jsonArrowRegex = '/([a-zA-Z0-9._-]+)\s*->>\s*([\'"])(.+?)\2/';
        preg_match_all($jsonArrowRegex, $query, $matches);
        foreach ($matches[0] as $i => $jsonArrow) {
            $field = $matches[1][$i];
            $quote = $matches[2][$i];
            $path = $matches[3][$i];
            // $replacement = "JSON_UNQUOTE(JSON_EXTRACT($field, $quote{$path}$quote))";
            $replacement = "IF(JSON_EXTRACT($field, $quote{$path}$quote) = 'null', NULL, JSON_UNQUOTE(JSON_EXTRACT($field, $quote{$path}$quote)))";
            $query = str_replace($jsonArrow, $replacement, $query);
        }

        return $query;
    }


    /*
    Given an object
    {
        "id": "record.id",
        "fecha": "record.data->>'$.fecha'",
        "data": {"$json_decode": "record.data"},

        "person": {
            "id": "person.id",
            "firstname": "person.firstname",
            "lastname": "person.lastname",
            "gender": "person.gender"
        },

        "responsible": {
            "id": "responsible.id",
            "firstname": "responsible.firstname",
            "lastname": "responsible.lastname",
            "gender": "responsible.gender"
        }
    }

    Produces an array of columns to be used in the "select" property of the query
    [
        {"value": "record.id", "as": "_result.id"},
        {"value": "record.data->>'$.fecha'", "as": "_result.fecha"},
        {"value": "record.data", "as": "_result.data"},
        {"value": "person.id", "as": "_result.person.id"},
        {"value": "person.firstname", "as": "_result.person_firstname"},
        {"value": "person.lastname", "as": "_result.person.lastname"},
        {"value": "person.gender", "as": "_result.person.gender"},
        {"value": "responsible.id", "as": "_result.responsible.id"},
        {"value": "responsible.firstname", "as": "_result.responsible.firstname"},
        {"value": "responsible.lastname", "as": "_result.responsible.lastname"},
        {"value": "responsible.gender", "as": "_result.responsible.gender"}
    ]
    */
    private static function getSelectColumns($object, &$allColumns = [], $prefix = "_result.")
    {
        foreach ($object as $propName => $value) {
            if (isset($value->{'$json_decode'})) {
                $allColumns[] = (object)[
                    "value" => $value->{'$json_decode'},
                    "as" => $prefix . $propName
                ];
            } elseif (is_object($value)) {
                self::getSelectColumns($value, $allColumns, "{$prefix}{$propName}.");
            } else {
                $allColumns[] = (object)[
                    "value" => $value,
                    "as" => $prefix . $propName
                ];
            }
        }

        return $allColumns;
    }

    /*
    Given a SELECT object
    {
        "id": "record.id",
        "fecha": "record.data->>'$.fecha'",
        "data": {"$json_decode": "record.data"},

        "person": {
            "id": "person.id",
            "firstname": "person.firstname",
            "lastname": "person.lastname",
            "gender": "person.gender"
        },

        "responsible": {
            "id": "responsible.id",
            "firstname": "responsible.firstname",
            "lastname": "responsible.lastname",
            "gender": "responsible.gender"
        }
    }
    and knowing that, through getSelectColumns, the db result is an assoc array like:
    [
        '_result.id' => 'record id',
        '_result.fecha' => 'What record.data->>'$.fecha' returned',
        '_result.data' => 'json data string',
        '_result.responsible.id' => 'Repsonsible id',
        '_result.responsible.firstname' => 'Some firstname',
        '_result.person.id' => 'PersonID',
        '_result.person.firstname' => 'Person name x',
    ]

    Create a valid object to be used in Utils::arrayToObject():
    {
        "id": "_result.id",
        "fecha": "_result.fecha",
        "data": {"$json_decode": "_result.data"},
        "person": {
            "id": "_result.person.id",
            "firstname": "_result.person.firstname"
        }
    }

    in other words, populate the object with "_result.XXXX" as its prop values
    */
    private static function toTransformationObject($object, $prefix = "_result.")
    {
        $result = new \stdClass;
        foreach ($object as $propName => $value) {
            if (isset($value->{'$json_decode'})) {
                $result->{$propName} = (object)['$json_decode' => "{$prefix}{$propName}"];
            } elseif (is_object($value)) {
                //  value is a nested object
                $result->{$propName} = self::toTransformationObject($value, "{$prefix}{$propName}.");
            } else {
                // otherwise, use the value as a key to retrieve value from the associative array
                $result->{$propName} = "{$prefix}{$propName}";
            }
        }
        return $result;
    }
}
