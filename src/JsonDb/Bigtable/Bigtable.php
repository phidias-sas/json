<?php

namespace Phidias\JsonDb\Bigtable;

class Bigtable extends \Phidias\JsonDb\Source
{
    public $baseName; // prefijo de las tablas en la DB:  baseName_indexes y baseName_records
    public $db;       // identificador de la DB (ej 'v3')

    public function __construct($baseName, $dbIdentifier = null)
    {
        $this->baseName = $baseName;
        $this->db = $dbIdentifier;
    }

    public function getTable($tableName, $indexableProperties = null)
    {
        return new Table($this, $tableName, $indexableProperties);
    }
}
