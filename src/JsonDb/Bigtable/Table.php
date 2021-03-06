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

    public function translateFieldName($fieldName)
    {
        if ($fieldName == "id") {
            return "customId";
        } else if (substr($fieldName, 0, 7) == "record.") {
            return substr($fieldName, 7);
        } else {
            return "JSON_EXTRACT(data, '$.$fieldName')";
        }
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
        return $this->getRecordCollection()
            ->allAttributes()
            ->match("customId", $recordId)
            ->fetch();
    }

    public function insert($incomingData, $authorId = null)
    {
        $retval = [];
        $targetRecords = is_array($incomingData) ? $incomingData : [$incomingData];

        $records = $this->getRecordCollection()->allAttributes();
        $db = $records->getDb();
        $db->query("SET autocommit = 0");
        $db->query("START TRANSACTION");

        foreach ($targetRecords as $data) {
            $record = new \stdClass;
            $record->id = $records->getUniqueId();

            $data->id = isset($data->id) ? $data->id : $record->id;

            $record->customId = $data->id;
            $record->tableId = $this->tableName;
            $record->data = $data;
            $record->dateCreated = time();
            $record->authorId = $authorId;
            $record->keywords = "";
            $record->dateModified = null;
            $record->dateDeleted = null;

            $records->insert($record); // Esto modifica $record->data con el valor insertado tal cual (es decir, lo deja como STRING)

            $record->data = json_decode($record->data);
            $retval[] = $record;

            // Crear indices
            if (is_array($this->indexableProperties)) {
                $indexValues = [];
                foreach ($this->indexableProperties as $propName) {
                    $indexValues[$propName] = JsonUtils::getProperty($data, $propName);
                }
                $this->putIndexes($record->id, $indexValues);
            }
        }

        $db->query("COMMIT");

        return $retval;
    }

    public function insertUpdate($incomingData, $authorId = null)
    {
        $retval = [];
        $incomingRecords = is_array($incomingData) ? $incomingData : [$incomingData];

        $existingRecords = [];
        $recordIds = [];
        foreach ($incomingRecords as $record) {
            if (isset($record->id)) {
                $recordIds[] = $record->id;
            }
        }
        if (count($recordIds)) {
            $probe = $this->getRecordCollection()
                ->allAttributes()
                ->match("customId", $recordIds)
                ->find();
            foreach ($probe as $record) {
                $existingRecords[$record->customId] = $record;
            }
        }

        $changedRecords = [];
        $newRecords = [];

        foreach ($incomingRecords as $record) {
            if (!isset($record->id) || !isset($existingRecords[$record->id])) {
                $newRecords[] = $record;
                continue;
            }

            $current = $existingRecords[$record->id];
            $hasChanges = false;
            foreach ($record as $propName => $incomingValue) {
                // $diff = $current->data->$propName != $incomingValue;
                $diff = json_encode($current->data->$propName) != json_encode($incomingValue);
                if (!isset($current->data->$propName) || $diff) {
                    $current->data->$propName = $incomingValue;
                    $hasChanges = true;
                }
            }

            if ($hasChanges) {
                $changedRecords[] = $current;
            } else {
                $retval[] = $current->data; // unchanged
            }
        }

        $records = $this->getRecordCollection()->allAttributes();
        $db = $records->getDb();
        $db->query("SET autocommit = 0");
        $db->query("START TRANSACTION");

        foreach ($newRecords as $data) {
            $newRecord = new \stdClass;
            $newRecord->id = $records->getUniqueId();
            $data->id = isset($data->id) ? $data->id : $newRecord->id;
            $newRecord->customId = $data->id;
            $newRecord->tableId = $this->tableName;
            $newRecord->data = $data;
            $newRecord->dateCreated = time();
            $newRecord->authorId = $authorId;
            $newRecord->keywords = "";
            $newRecord->dateModified = null;
            $newRecord->dateDeleted = null;

            $records->insert($newRecord); // Esto modifica $newRecord->data con el valor insertado tal cual (es decir, lo deja como STRING)
            $newRecord->data = json_decode($newRecord->data);

            $retval[] = $newRecord->data;

            // Crear indices
            if (is_array($this->indexableProperties)) {
                $indexValues = [];
                foreach ($this->indexableProperties as $propName) {
                    $indexValues[$propName] = JsonUtils::getProperty($newRecord->data, $propName);
                }
                $this->putIndexes($newRecord->id, $indexValues);
            }
        }

        foreach ($changedRecords as $record) {
            $customId = isset($record->data->id) ? $record->data->id : $record->id;
            $record->data->id = $customId;

            $this->getRecordCollection()
                ->attributes(["customId", "data", "dateModified", "authorId", "keywords"]) // establecer los atributos que se pueden modificar
                ->match("id", $record->id)
                ->set("customId", $customId)
                ->set("data", json_encode($record->data))
                ->set("dateModified", time())
                ->set("authorId", $authorId)
                // ->set("keywords", $keywords)
                ->update();

            $retval[] = $record->data;

            // Actualizar indices
            if (is_array($this->indexableProperties)) {
                $indexValues = [];
                foreach ($this->indexableProperties as $propName) {
                    $indexValues[$propName] = JsonUtils::getProperty($record->data, $propName);
                }

                if (count($indexValues)) {
                    $this->deleteIndex($record->id);
                    $this->putIndexes($record->id, $indexValues);
                }
            }
        }

        $db->query("COMMIT");

        return $retval;
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
                ->set("dateModified", $record->dateModified)
                ->set("authorId", $authorId)
                // ->set("keywords", $keywords)

                ->update();


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

    private function putIndexes($recordId, $indexValues = null)
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

                $indexCollection->insert($newIndex);
            }
        }
    }

    private function deleteIndex($recordId, $keyName = null)
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
        $conditionSql = $this->evaluateWhere($condition);
        $this->collection->where($conditionSql);

        return $this;
    }

    public function attribute($attributeName, $attributeSource = null)
    {
        $attributeName = trim($attributeName);
        if (!$attributeName) {
            throw new \Exception("Attribute name cannot be empty");
        }

        $this->attributes[$attributeName] = $attributeName;

        if ($attributeName == "id") {
            $this->collection->attribute("customId");
        } else if (substr($attributeName, 0, 7) == "record.") {
            $this->collection->attribute(substr($attributeName, 7));
        } else if ($attributeName == "*") {
            $this->useAllAttributes = true;
            $this->collection->attribute("data");
        } else if ($attributeSource == null) {
            $this->collection->attribute("x.$attributeName", "JSON_EXTRACT(data, '$.$attributeName')");
        } else {
            $this->collection->attribute($attributeName, $attributeSource);
        }

        return $this;
    }

    public function match($attributeName, $attributeValue)
    {
        if ($attributeName == "id") {
            $this->collection->match("customId", $attributeValue);
        } else if (substr($attributeName, 0, 7) == "record.") {
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

    public function page($page)
    {
        $this->collection->page($page);
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

        // siempre traer ID (y atributos que identifican un ON con un padre?)
        $this->collection->attribute("customId");

        foreach ($this->collection->find()->fetchAll() as $record) {
            if ($this->useAllAttributes) {
                $retvalItem = isset($record->data) && is_object($record->data) ? $record->data : new \stdClass;
            } else {
                $retvalItem = new \stdClass;
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

            $retvalItem->id = $record->customId; // PLOP id
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
