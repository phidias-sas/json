<?php

namespace Phidias\JsonDb\Connector\DbEntity;

class Database extends \Phidias\JsonDb\Database
{
    public function getTable($tableName, $indexableProperties = null)
    {
        return new Table($tableName);
    }
}
