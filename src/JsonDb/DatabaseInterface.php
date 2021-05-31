<?php

namespace Phidias\JsonDb;

interface DatabaseInterface
{
    public function __construct($settings = null);
    public function getTable($tableName);
    public function query($query);
}