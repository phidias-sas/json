<?php

namespace Phidias\JsonDb\Connector\Bigtable\Index;

class Entity extends \Phidias\Db\Orm\Entity
{
    protected static $schema = [
        "table" => "phidias_jsondb_indexes",
        "keys" => ["tableId", "recordId", "keyName", "keyValue"],

        "attributes" => [
            "tableId" => [
                "type" => "varchar",
                "length" => 32,
                "acceptNull" => false,
            ],

            "recordId" => [
                "entity" => "Phidias\JsonDb\Connector\Bigtable\Record\Entity",
                "onDelete" => "CASCADE",
                "onUpdate" => "CASCADE",
                "acceptNull" => false,
            ],

            "keyName" => [
                "type" => "varchar",
                "length" => 32,
                "acceptNull" => false,
            ],

            "keyValue" => [
                "type" => "varchar",
                "length" => 32,
                "acceptNull" => true,
            ]
        ],

        // "indexes" => [
        //     "tableId" => "tableId"
        // ]
    ];
}
