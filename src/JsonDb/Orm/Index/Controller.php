<?php

namespace Phidias\JsonDb\Orm\Index;

use Phidias\JsonDb\Orm\Index\Entity as Index;

class Controller
{
    public static function delete($tableId, $recordId, $keyName = null)
    {
        $targets = Index::collection()
            ->match("tableId", $tableId)
            ->match("recordId", $recordId);

        if ($keyName) {
            $targets->match("keyName", $keyName);
        }

        return $targets->delete();
    }

    public static function put($tableId, $recordId, $keyName, $keyValue = null)
    {
        self::delete($tableId, $recordId, $keyName);

        $values = is_array($keyValue) ? $keyValue : [$keyValue];

        $indexes = Index::collection()
            ->allAttributes()
            ->set("tableId", $tableId)
            ->set("keyName", $keyName)
            ->set("recordId", $recordId);

        foreach ($values as $value) {
            if (!$value) {
                continue;
            }

            $nIndex = new Index;
            $nIndex->keyValue = $value;
            $indexes->add($nIndex);
        }

        $indexes->save();
    }

    public static function filterCollection($collection, $tableId, $keyName, $keyValue)
    {
        $valueCondition = '';
        if (is_array($keyValue)) {
            if (!count($keyValue)) {
                $collection->where(0);
                return $collection;
            }

            $valueCondition = "`keyValue` IN :keyValue";
        } else {
            $valueCondition = "`keyValue` = :keyValue";
        }

        $indextTableName = Index::getSchema()->getTable();

        $collection->where("id IN (SELECT `recordId` FROM `$indextTableName` WHERE `tableId` = :tableId AND `keyName` = :keyName AND $valueCondition)", [
            "tableId" => $tableId,
            "keyName" => $keyName,
            "keyValue" => $keyValue,
        ]);

        return $collection;
    }
}
