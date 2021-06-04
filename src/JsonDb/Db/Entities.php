<?php

namespace Phidias\JsonDb\Db;

class Entities extends \Phidias\JsonDb\Source
{
    public function getTable($tableName, $indexableProperties = null)
    {
        return new Table($tableName);
    }
}
