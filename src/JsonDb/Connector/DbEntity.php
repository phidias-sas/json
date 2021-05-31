<?php

namespace Phidias\JsonDb\Connector;

class DbEntity implements \Phidias\JsonDb\DatabaseInterface
{
    public function __construct($settings = null)
    {
    }

    public function getTable($tableName)
    {
        return new DbEntityTable($tableName);
    }

    // Pues este es el mimso metodo que Phidias\JsonDb\Database->query
    // tal vez deberia estar en una clase en vez de una interfaz ?
    public function query($query)
    {
        $dataset = new \Phidias\JsonDb\Dataset;
        $dataset->addDatabase('default', $this);

        return $dataset->query($query);
    }
}
