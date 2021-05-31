<?php

namespace Phidias\Core\Vm\Plugins;

class Orm extends \Phidias\Core\Vm\Plugin
{
    public static function install($vm)
    {
        $vm->defineFunction('db.entity', [self::class, 'fetchEntity']);
        $vm->defineFunction('orm.query.select', [self::class, 'querySelect']);
    }

    public static function fetchEntity($args)
    {
        $table = isset($args->from) ? $args->from : null;

        if (!$table) {
            throw new \Exception("no table specified");
        }

        if (!is_a($table, '\Phidias\Db\Orm\Entity', true)) {
            throw new \Exception("invalid entity");
        }

        $objEntity = new $table;

        $collection = $objEntity::collection()
            ->attributes(array_keys(get_object_vars($args->properties)))
            ->match($args->match)
            ->limit(1);

        return $collection->find()->first();
        // return $collection->getQuery()->toSQL();
    }

    public static function querySelect($args)
    {
        $table = isset($args->from) ? $args->from : null;

        if (!$table) {
            throw new \Exception("no table specified");
        }

        $query = new \stdClass;
        if (isset($args->match)) {
            $query->match = $args->match;
        }

        if (isset($args->limit)) {
            $query->limit = $args->limit;
        } else {
            $query->limit = 100;
        }

        $collection = \Phidias\V3\Orm\Table\Controller::getRecords($table, $query);
        return $collection->find()->fetchAll();
        // return $collection->getQuery()->toSQL();
    }
}
