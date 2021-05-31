<?php

namespace Phidias\JsonDb;

class Database
{
    public function getTable($tableName, $indexableProperties = null)
    {
        return new Table($tableName, $indexableProperties);
    }

    public function query($query)
    {
        $dataset = new Dataset;
        $dataset->addDatabase('default', $this);

        return $dataset->query($query);
    }
}
