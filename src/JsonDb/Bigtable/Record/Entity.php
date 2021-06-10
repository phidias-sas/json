<?php

namespace Phidias\JsonDb\Bigtable\Record;

class Entity extends \Phidias\Db\Orm\Entity
{
    protected static $schema = [
        "table" => "phidias_jsondb_records",
        "keys" => ["id"],

        "attributes" => [
            "id" => [
                "type" => "uuid",
                "length" => 32,
                "acceptNull" => false,
            ],

            "tableId" => [
                "type" => "varchar",
                "length" => 32,
                "acceptNull" => false,
            ],

            "customId" => [
                "type" => "varchar",
                "length" => 32,
                "acceptNull" => false,
            ],

            "data" => [
                "type" => "json",
                "acceptNull" => true,
            ],

            "keywords" => [
                "type" => "MEDIUMTEXT",
                "acceptNull" => true,
                "default" => null,
            ],

            "authorId" => [
                "type" => "varchar",
                "length" => 32,
                "acceptNull" => true,
                "default" => null,
            ],

            "dateCreated" => [
                "type" => "integer",
                "length" => 11,
                "acceptNull" => true,
                "default" => null,
            ],

            "dateModified" => [
                "type" => "integer",
                "length" => 11,
                "acceptNull" => true,
                "default" => null,
            ],

            "dateDeleted" => [
                "type" => "integer",
                "length" => 11,
                "acceptNull" => true,
                "default" => null,
            ],
        ],

        "indexes" => [
            "tableId" => "tableId",
            "customId" => "customId",
            "authorId" => "authorId",
            // "keywords" => "keywords", // Debe ser un indice FULLTEXT
            // ALTER TABLE `phidias_jsondb_records` ADD FULLTEXT(`keywords`);
        ]
    ];
}
