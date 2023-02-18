<?php

namespace Phidias\Json;

class Csv
{
    public static function download($records, $filename = null, $separator = ";")
    {
        if (!$filename) {
            $filename = date("dmY_Hi") . '.csv';
        }

        if (substr($filename, -4) != '.csv') {
            $filename .= '.csv';
        }

        header("Content-Type: application/csv");
        header("Content-Disposition: attachment; filename={$filename}");
        header("Pragma: no-cache");

        echo self::toCSV($records, $separator);
        exit;
    }


    /*
    Given an array of arbitrary objects
    [
        {
            "id": "w0m1byu7taa",
            "fecha": "2023-02-17T16:00",
            "lol": "LOL 2023-02-17T16:00",
            "fun": "2023-02-17T16:00 EH!79380921",
            "data": {
                "fecha": "2023-02-17T16:00",
                "motivo": "pio",
                "nombre": "pio"
            },
            "person": {
                "id": "1",
                "firstname": "Hugo",
            },
            "responsible": {
                "id": "2",
                "firstname": "Paco",
            }
        },
        {
            "another": "object"
        }
    ]

    Generates a CSV string, using ALL UNIQUE properties as column names
    e.g.

    id;fecha;lol;fun;data.fecha;data.motivo;data.nombre;person.id;person.firstname;responsible.id;responsible.lastname
    "w0m1byu7taa";"2023-02-17T16:00"";"LOL 2023-02-17T16:00";"2023-02-17T16:00 EH!79380921";"2023-02-17T16:00";"pio";"pio";"1";"Hugo";"2";"Paco;""
    ;;;;;;;;;;;"object"
    ...
    */
    public static function toCSV($records, $separator = ";")
    {
        if (!count($records)) {
            return "";
        }

        $csv = "";
        $headers = self::getHeaders($records[0]); // Obtain headers from first record
        $csv .= self::toLine($headers, $separator);

        foreach ($records as $record) {
            $row = self::flatten($record);
            $csv .= self::toLine($row, $separator);
        }

        // Add UTF-8 Byte Order Mask (BOM) at the beggining
        return "\xEF\xBB\xBF" . $csv;
    }

    // Helper function to flatten the record into a single-level array
    /*
    given
    {
        id: 'some id',
        fecha: 'some fecha',
        person: {
            id: 'some person id',
            firstname: 'some firstname',
            lastname: 'some lastname'
        }
    }

    returns
    [
        'some id',
        'some fecha',
        'some person id',
        'some firstname',
        'some lastname',
        ....
    ]
    */
    private static function flatten($record)
    {
        $result = [];
        foreach ($record as $value) {
            if (is_object($value)) {
                $result = array_merge($result, self::flatten((array)$value));
            } else {
                $result[] = $value;
            }
        }
        return $result;
    }


    /*
    given
    {
        id: 'some id',
        fecha: '...',
        person: {
            id: ...
            firstname: ...
            lastname: ...
        }
    }

    returns
    [
        'id',
        'fecha',
        'person.id',
        'person.firstname',
        'person.lastname',
        ....
    ]
    */
    private static function getHeaders($record)
    {
        $headers = [];

        // Extract headers from each property
        foreach ($record as $key => $value) {
            if (is_object($value)) {
                $subHeaders = self::getHeaders($value);
                foreach ($subHeaders as $subHeader) {
                    $headers[] = $key . '.' . $subHeader;
                }
            } else {
                $headers[] = $key;
            }
        }

        return $headers;
    }

    private static function toLine($arrLine, $separator)
    {
        $output = [];
        foreach ($arrLine as $value) {
            if (is_array($value)) {
                $value = implode(", ", $value);
            }

            $output[] = '"' . str_replace('"', '""', $value) . '"';
        }

        return implode($separator, $output) . "\n";
    }
}
