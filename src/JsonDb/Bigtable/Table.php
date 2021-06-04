<?php

namespace Phidias\JsonDb\Bigtable;

use \Phidias\JsonDb\Bigtable\Record\Entity as Record;
use \Phidias\JsonDb\Bigtable\Index\Entity as Index;

use Phidias\JsonVm\Utils as JsonUtils;

class Table extends \Phidias\JsonDb\Table
{
    private $source;

    private $tableName;
    private $attributes;
    private $collection;

    private $useAllAttributes;
    private $indexableProperties;

    public function __construct($source, $tableName, $indexableProperties = [])
    {
        $this->source = $source;

        $this->tableName = $tableName;
        $this->indexableProperties = $indexableProperties;
        $this->attributes = [];
        $this->useAllAttributes = false;

        $this->collection = $this->getRecordCollection()
            ->attribute("id")
            ->match("tableId", $this->tableName);
    }

    private function getRecordCollection()
    {
        $schema = Record::getSchema();
        if ($this->source) {
            $schema->db($this->source->db);
            $schema->table($this->source->baseName . '_records');
        }
        $collection = new \Phidias\Db\Orm\Collection($schema);

        return $collection;
    }

    private function getIndexCollection()
    {
        $schema = Index::getSchema();
        if ($this->source) {
            $schema->db($this->source->db);
            $schema->table($this->source->baseName . '_indexes');
        }
        $collection = new \Phidias\Db\Orm\Collection($schema);

        return $collection;
    }

    public function getRecord($recordId)
    {
        return $this->getRecordCollection()->allAttributes()->fetch($recordId);
    }

    public function insert($data, $authorId = null)
    {
        $record = new \stdClass;
        $record->tableId = $this->tableName;
        $record->data = $data;
        $record->dateCreated = time();
        $record->authorId = $authorId;
        // $record->keywords = "";

        $this->getRecordCollection()
            ->allAttributes()
            ->save($record);

        // Crear indices
        if (is_array($this->indexableProperties)) {
            $indexValues = [];
            foreach ($this->indexableProperties as $propName) {
                $indexValues[$propName] = JsonUtils::getProperty($record->data, $propName);
            }
            $this->putIndexes($record->id, $indexValues);
        }

        return $record;
    }

    public function update($recordId, $data, $authorId = null)
    {
        // Primero hacer un GET (tira una exception si no existe)
        $record = $this->getRecordCollection()->allAttributes()->fetch($recordId);

        if (!is_object($data)) {
            $data = $data ? json_decode(json_encode($data)) : new \stdClass;
        }

        // Actualizar objeto de datos
        $hasChanges = false;
        foreach ($data as $propName => $incomingValue) {
            if (!isset($record->data->$propName) || $record->data->$propName != $incomingValue) {
                $record->data->$propName = $incomingValue;
                $hasChanges = true;
            }
        }

        if ($hasChanges) {
            $record->dateModified = time();

            // $keywords = self::getKeywords($record->data, ["card.text", "card.secondary"]);

            $this->getRecordCollection()
                ->attributes(["data", "dateModified", "authorId"]) // establecer los atributos que se pueden modificar
                ->match("id", $record->id)

                ->set("data", json_encode($record->data))
                // ->set("keywords", $keywords)
                ->set("dateModified", $record->dateModified)
                ->set("authorId", $authorId)

                ->update();
        }

        // Actualizar indices
        if (is_array($this->indexableProperties)) {
            $indexValues = [];
            foreach ($this->indexableProperties as $propName) {
                $indexValues[$propName] = JsonUtils::getProperty($record->data, $propName);
            }

            if (count($indexValues)) {
                $this->deleteIndex($recordId);
                $this->putIndexes($recordId, $indexValues);
            }
        }

        return $record;
    }

    public function delete($recordId)
    {
        // Primero hacer un GET (tira una exception si no existe)
        $record = $this->getRecordCollection()->allAttributes()->fetch($recordId);

        $this->getRecordCollection()
            ->match("tableId", $this->tableName)
            ->match("id", $recordId)
            ->limit(1)
            ->delete();

        return $record; // fare thee well
    }

    public function putIndexes($recordId, $indexValues = null)
    {
        if (!$indexValues || !is_array($indexValues)) {
            return;
        }

        $indexCollection = $this->getIndexCollection()
            ->allAttributes();

        foreach ($indexValues as $keyName => $keyValue) {
            // Si el valor a indexar es un arreglo, se indexa el valor de cada item (siempre y cuando sea scalar)
            $targetKeyValues = !is_array($keyValue) ? [$keyValue] : $keyValue;
            foreach ($targetKeyValues as $targetValue) {
                if (!is_scalar($targetValue)) {
                    continue;
                }

                $newIndex = new \stdClass;
                $newIndex->tableId = $this->tableName;
                $newIndex->recordId = $recordId;
                $newIndex->keyName = $keyName;
                $newIndex->keyValue = $targetValue;

                $indexCollection->add($newIndex);
            }
        }

        return $indexCollection->save();
    }

    public function deleteIndex($recordId, $keyName = null)
    {
        $indexes = $this->getIndexCollection()
            ->match("tableId", $this->tableName)
            ->match("recordId", $recordId);

        if ($keyName) {
            $indexes->match("keyName", $keyName);
        }

        return $indexes->delete();
    }


    public function where($condition)
    {
        $vm = new \Phidias\JsonVm\Vm();
        $vm->addPlugin(new JsonVmPlugin);

        $parsedCondition = $vm->evaluate($condition);
        $this->collection->where($parsedCondition);

        return $this;
    }

    public function attribute($attributeName)
    {
        $attributeName = trim($attributeName);
        if (!$attributeName) {
            throw new \Exception("Attribute name cannot be empty");
        }

        $this->attributes[$attributeName] = $attributeName;

        if (substr($attributeName, 0, 7) == "record.") {
            $this->collection->attribute(substr($attributeName, 7));
        } else if ($attributeName == "*") {
            $this->useAllAttributes = true;
            $this->collection->attribute("data");
        } else {
            $this->collection->attribute("x.$attributeName", "JSON_EXTRACT(data, '$.$attributeName')");
        }

        return $this;
    }

    public function match($attributeName, $attributeValue)
    {
        if (substr($attributeName, 0, 7) == "record.") {
            $this->collection->match(substr($attributeName, 7), $attributeValue);
        } else {
            $valueCondition = '';
            if (is_array($attributeValue)) {
                if (!count($attributeValue)) {
                    $this->collection->where(0);
                    return $this;
                }
                $valueCondition = "`keyValue` IN :keyValue";
            } else {
                $valueCondition = "`keyValue` = :keyValue";
            }

            $indextTableName = $this->source->baseName . '_indexes';
            $this->collection->where("id IN (SELECT `recordId` FROM `$indextTableName` WHERE `tableId` = :tableId AND `keyName` = :keyName AND $valueCondition)", [
                "tableId" => $this->tableName,
                "keyName" => $attributeName,
                "keyValue" => $attributeValue
            ]);
        }

        return $this;
    }

    public function limit($limit)
    {
        $this->collection->limit($limit);
        return $this;
    }

    public function order($order)
    {
        $this->collection->order($order);
        return $this;
    }

    public function fetch()
    {
        $retval = [];

        foreach ($this->collection->find()->fetchAll() as $record) {
            if ($this->useAllAttributes) {
                $retvalItem = isset($record->data) && is_object($record->data) ? $record->data : new \stdClass;
            } else {
                $retvalItem = new \stdClass;
                $retvalItem->id = $record->id; // PLOP id (initial id)
            }

            foreach ($this->attributes as $attributeName) {
                if (substr($attributeName, 0, 7) == "record.") {
                    $attrName = substr($attributeName, 7);
                    $retvalItem->$attributeName = isset($record->$attrName) ? $record->$attrName : null;
                } else if (isset($record->{"x." . $attributeName})) {
                    $retvalItem->$attributeName = json_decode($record->{"x." . $attributeName});
                    unset($record->{"x." . $attributeName});
                } else {
                    // Se habia solicitado el atributo mediante this->attribute
                    // pero en ningun lado de los resultados se puede encontrar
                    // $retvalItem->$attributeName = null;
                }
            }

            // PLOP id
            if (!isset($retvalItem->id)) {
                $retvalItem->id = $record->id;
            }

            $retval[] = $retvalItem;
        }

        return $retval;
    }

    public function sql($query, $params = null)
    {
        $this->collection->query($query, $params);
        return $this;
    }


    /// Funciones migradas de _OldConnectorController
    public function search($searchString)
    {
        $searchString = str_replace(["(", ")"], "", $searchString);
        $searchString = str_replace("'", "\'", $searchString);

        $this->collection->attribute("score", "MATCH(keywords) AGAINST('$searchString' IN BOOLEAN MODE)")
            ->having("`score` > 0")
            ->order("`score` DESC");

        return $this;
    }

    /**
     * Esta funcion recibe un objeto arbitrario
     *
     * foo: {
     *   things: ['one', 'two'],
     *   firstName: 'Santiago',
     *   stuff: {
     *     word1: 'Hola',
     *     word2: 'Mundo'
     *   }
     * }
     *
     * y una lista ($attrs) de paths simples indicando propiedades del objeto
     *
     * $attrs = ['things[1]', 'firstName', 'stuff.word2']
     *
     * y genera una CADENA con todos los valores de esas propiedades separadas por espacios
     * <<
     * "two Santiago Mundo"
     */
    private static function getKeywords($objData, $attrs = [])
    {
        $words = [];
        foreach ($attrs as $attr) {
            $word = JsonUtils::getProperty($objData, $attr);
            if (!is_string($word)) {
                // $word = json_encode($word);
                continue;
            }

            $words[] = $word;
        }

        return trim(implode(" ", $words));
    }
}
