<?php

namespace Phidias\JsonDb\Orm;

use Phidias\Core\Data\Utils as DataUtils; // damn

use Phidias\JsonDb\Orm\Record\Entity as Record;
use Phidias\JsonDb\Orm\Index\Controller as Indexes;

class Controller
{
    public static function getRecords($tableId, $query = null)
    {
        if (is_array($query)) { // convertir a objeto cuando es un arreglo asociativo
            $query = json_decode(json_encode($query), FALSE);
        }

        $records = Record::collection()
            ->attributes(["id", "data", "dateCreated", "dateModified", "authorId"])
            ->match("tableId", $tableId);

        if (isset($query->match)) {
            foreach ($query->match as $keyName => $keyValue) {
                if ($keyName == 'id' || $keyName == 'authorId') {
                    $records->match($keyName, $keyValue);
                } else {
                    Indexes::filterCollection($records, $tableId, $keyName, $keyValue);
                }
            }
        }

        $search = isset($query->search) ? trim($query->search) : null;
        if ($search) {
            $search = str_replace(["(", ")"], "", $search);
            $search = str_replace("'", "\'", $search);
            $records->attribute("score", "MATCH(keywords) AGAINST('$search'  IN BOOLEAN MODE)");
            $records->having("`score` > 0");
            $records->order("`score` DESC");
        }

        $limit = isset($query->limit) ? max(1, $query->limit) : 100;
        $records->limit($limit);

        $page = isset($query->page) ? max(1, $query->page) : 1;
        $records->page($page);

        // dumpx($records->getQuery()->toSQL());
        return $records;
    }

    public static function findRecord($tableId, $indexes)
    {
        if (is_array($indexes)) {
            $indexes = json_decode(json_encode($indexes), FALSE);
        }

        $records = Record::collection()
            ->attributes(["id", "data", "dateCreated", "dateModified", "authorId"])
            ->match("tableId", $tableId);

        foreach ($indexes as $keyName => $keyValue) {
            Indexes::filterCollection($records, $tableId, $keyName, $keyValue);
        }

        $records->limit(1);
        return $records->find()->first();
    }

    public static function postRecord($tableId, $data, $indexableProperties = null, $authorId = null)
    {
        if (!$data) {
            throw new \Exception("postRecord: data is empty");
        }

        // Convertir arreglos de php a objetos
        $data = json_decode(json_encode($data));

        /*
        Esta opcion,  insertar multiples registros con un arreglo,
        tal vez sea mejor hacerla como un metodo aparte (postMultiple?),  pues
        en teoria un registro SI puede ser un arreglo, y ademas se se podría (?) implementar
        un proceso optimizado para inserciones multiples de forma mas eficiente
        (en vez de repetir este, que inserta uno a uno)
        */
        // if (is_array($data)) {
        //     $retval = [];
        //     foreach ($data as $item) {
        //         $retval[] = self::postRecord($tableId, $item, $indexableProperties, $authorId);
        //     }
        //     return $retval;
        // }

        $record = new Record;
        $record->tableId = $tableId;
        $record->data = $data;
        $record->dateCreated = time();
        $record->authorId = $authorId;
        $record->save();

        if (is_array($indexableProperties)) {
            foreach ($indexableProperties as $propertyName) {
                $propertyValue = DataUtils::getProperty($record->data, $propertyName);
                if ($propertyValue) {
                    Indexes::put($tableId, $record->id, $propertyName, $propertyValue);
                }
            }
        }

        // plop ID
        if (is_object($record->data) && !isset($record->data->id)) {
            $record->data->id = $record->id;
        }

        return $record;
    }

    public static function getRecord($tableId, $recordId, $query = null)
    {
        return new Record($recordId);
    }

    public static function putRecord($tableId, $recordId, $query = null, $input = null, $authorId = null)
    {
        try {
            $record = new Record($recordId);
        } catch (\Exception $e) {
            $record = new Record;
            $record->id = $recordId;
            $record->tableId = $tableId;
            $record->dateCreated = time();
            $record->authorId = $authorId;
        }

        $record->data = $input;
        $record->dateModified = time();
        $record->keywords = self::getKeywords($record->data, ["card.text", "card.secondary"]);

        $record->save();

        if (isset($query->index)) {
            $indexableProperties = is_array($query->index) ? $query->index : [$query->index];
            foreach ($indexableProperties as $propertyName) {
                $propertyValue = DataUtils::getProperty($record->data, $propertyName);
                Indexes::put($tableId, $record->id, $propertyName, $propertyValue);
            }
        }

        return $record;
    }

    public static function deleteRecord($tableId, $recordId)
    {
        $record = new Record($recordId);
        $record->delete();

        Indexes::delete($tableId, $recordId);

        return $record;
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
     * $attrs = ['things[0]', 'stuff.word1', 'stuff.word2']
     *
     * y genera una cadena con todos los valores de esas propiedades concatenados
     * <<
     * "one Hola Mundo"
     */
    private static function getKeywords($objData, $attrs = [])
    {
        $words = [];

        $words[] = DataUtils::getProperty($objData, "card.text");
        $words[] = DataUtils::getProperty($objData, "card.secondary");

        $redacciones = DataUtils::getProperty($objData, "redacciones");
        if (is_array($redacciones)) {
            foreach ($redacciones as $redaccion) {
                $words[] = trim($redaccion->texto);
            }
        }

        $blocks = DataUtils::getProperty($objData, "body.blocks");
        if (is_array($blocks)) {
            foreach ($blocks as $block) {
                if ($block->component == "CmsMediaHtml" && isset($block->props->value)) {
                    $words[] = trim(strip_tags($block->props->value));
                }
            }
        }

        return trim(implode(" ", $words));
    }
}
