<?php

/**
 * SpreadsheetReader.php
 * Reads rows out of a .csv or .xlsx file without any external library -
 * just PHP's built-in fgetcsv() for CSV, and ZipArchive + SimpleXML for
 * XLSX (an .xlsx file is just a zip of XML files).
 *
 * .xls (the old binary Excel 97-2003 format) is NOT supported: parsing
 * that binary format without a library like PhpSpreadsheet isn't
 * practical, so readXls() throws a clear exception telling the user to
 * re-save as .xlsx or .csv instead.
 */
class SpreadsheetReader
{
    /**
     * Reads a file and returns an array of rows, each row an array of
     * cell strings. The first row is assumed to be a header row.
     */
    public static function read(string $path, string $extension): array
    {
        return match (strtolower($extension)) {
            'csv' => self::readCsv($path),
            'xlsx' => self::readXlsx($path),
            'xls' => throw new RuntimeException(
                "The old .xls format isn't supported. Please re-save the file as .xlsx or .csv and try again."
            ),
            default => throw new RuntimeException("Unsupported file type: .$extension"),
        };
    }

    private static function readCsv(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if ($handle === false) {
            throw new RuntimeException("Could not open the CSV file.");
        }
        while (($row = fgetcsv($handle)) !== false) {
            $rows[] = $row;
        }
        fclose($handle);
        return $rows;
    }

    private static function readXlsx(string $path): array
    {
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException("The PHP zip extension is required to read .xlsx files. Please export as .csv instead.");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new RuntimeException("Could not open the .xlsx file — it may be corrupted.");
        }

        // Shared strings (xlsx stores repeated text values in a shared pool)
        $sharedStrings = [];
        $sharedXml = $zip->getFromName('xl/sharedStrings.xml');
        if ($sharedXml !== false) {
            $sharedDoc = simplexml_load_string($sharedXml);
            foreach ($sharedDoc->si as $si) {
                $sharedStrings[] = isset($si->t) ? (string) $si->t : implode('', array_map(fn($r) => (string) $r->t, $si->r ?? []));
            }
        }

        $sheetXml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException("Could not find worksheet data in the .xlsx file.");
        }

        $doc = simplexml_load_string($sheetXml);
        $rows = [];

        foreach ($doc->sheetData->row as $rowXml) {
            $row = [];
            $colIndex = 0;
            foreach ($rowXml->c as $cell) {
                // Cell references look like "A1", "B1"... derive the column index
                // so gaps (empty cells) don't shift the rest of the row.
                $ref = (string) $cell['r'];
                preg_match('/^([A-Z]+)/', $ref, $m);
                $thisCol = self::columnLetterToIndex($m[1] ?? '');
                while ($colIndex < $thisCol) {
                    $row[] = '';
                    $colIndex++;
                }

                $type = (string) $cell['t'];
                $value = isset($cell->v) ? (string) $cell->v : '';
                if ($type === 's' && $value !== '') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }
                $row[] = $value;
                $colIndex++;
            }
            $rows[] = $row;
        }

        return $rows;
    }

    private static function columnLetterToIndex(string $letters): int
    {
        $index = 0;
        foreach (str_split($letters) as $char) {
            $index = $index * 26 + (ord($char) - ord('A') + 1);
        }
        return $index - 1;
    }
}
