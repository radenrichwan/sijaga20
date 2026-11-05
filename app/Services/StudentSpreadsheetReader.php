<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use SimpleXMLElement;
use ZipArchive;

class StudentSpreadsheetReader
{
    /**
     * @return list<array{row: int, values: array<int, string>}>
     */
    public function read(UploadedFile $file): array
    {
        return strtolower($file->getClientOriginalExtension()) === 'xlsx'
            ? $this->readXlsx($file)
            : $this->readCsv($file);
    }

    /**
     * @return list<array{row: int, values: array<int, string>}>
     */
    private function readCsv(UploadedFile $file): array
    {
        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) {
            throw ValidationException::withMessages(['file' => 'File CSV tidak dapat dibaca.']);
        }

        $rows = [];
        $line = 0;
        try {
            while (($values = fgetcsv($stream, null, ',', '"', '\\')) !== false) {
                $line++;
                $rows[] = ['row' => $line, 'values' => array_map(static fn (mixed $value): string => (string) $value, $values)];
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    /**
     * @return list<array{row: int, values: array<int, string>}>
     */
    private function readXlsx(UploadedFile $file): array
    {
        $archive = new ZipArchive;
        if ($archive->open($file->getRealPath()) !== true) {
            throw ValidationException::withMessages(['file' => 'File Excel tidak valid atau tidak dapat dibuka.']);
        }

        try {
            $sheetPath = $this->firstWorksheetPath($archive);
            $sheetXml = $archive->getFromName($sheetPath);
            if ($sheetXml === false) {
                throw ValidationException::withMessages(['file' => 'File Excel tidak memiliki lembar kerja pertama yang dapat dibaca.']);
            }

            $sharedStrings = [];
            $sharedXml = $archive->getFromName('xl/sharedStrings.xml');
            if ($sharedXml !== false) {
                $sharedRoot = $this->parseXml($sharedXml);
                foreach ($sharedRoot->children('http://schemas.openxmlformats.org/spreadsheetml/2006/main')->si as $item) {
                    $sharedStrings[] = $this->spreadsheetText($item);
                }
            }

            $root = $this->parseXml($sheetXml);
            $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
            $rows = [];
            foreach ($root->children($namespace)->sheetData->children($namespace)->row as $row) {
                $rowNumber = (int) $row->attributes()['r'];
                $values = [];
                foreach ($row->children($namespace)->c as $cell) {
                    $attributes = $cell->attributes();
                    $column = $this->columnIndex((string) $attributes['r']);
                    $type = (string) $attributes['t'];
                    $cellChildren = $cell->children($namespace);
                    $value = match ($type) {
                        's' => $sharedStrings[(int) $cellChildren->v] ?? '',
                        'inlineStr' => $this->spreadsheetText($cellChildren->is),
                        default => (string) $cellChildren->v,
                    };
                    $values[$column] = $value;
                }

                if ($values !== []) {
                    ksort($values);
                    $rows[] = [
                        'row' => $rowNumber,
                        'values' => array_replace(array_fill(0, max(array_keys($values)) + 1, ''), $values),
                    ];
                }
            }

            return $rows;
        } finally {
            $archive->close();
        }
    }

    private function parseXml(string $xml): SimpleXMLElement
    {
        $root = simplexml_load_string($xml, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING);
        if ($root === false) {
            throw ValidationException::withMessages(['file' => 'Struktur file Excel tidak valid.']);
        }

        return $root;
    }

    private function firstWorksheetPath(ZipArchive $archive): string
    {
        $workbookXml = $archive->getFromName('xl/workbook.xml');
        $relationshipsXml = $archive->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relationshipsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = $this->parseXml($workbookXml);
        $relationships = $this->parseXml($relationshipsXml);
        $officeNamespace = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $mainNamespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $packageNamespace = 'http://schemas.openxmlformats.org/package/2006/relationships';
        $firstSheet = $workbook->children($mainNamespace)->sheets->children($mainNamespace)->sheet[0] ?? null;
        if ($firstSheet === null) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationshipId = (string) $firstSheet->attributes($officeNamespace)['id'];
        foreach ($relationships->children($packageNamespace)->Relationship as $relationship) {
            $attributes = $relationship->attributes();
            if ((string) $attributes['Id'] !== $relationshipId) {
                continue;
            }

            $target = ltrim((string) $attributes['Target'], '/');

            return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function spreadsheetText(SimpleXMLElement $element): string
    {
        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $children = $element->children($namespace);
        if (isset($children->t)) {
            return (string) $children->t;
        }

        $text = '';
        foreach ($children->r as $run) {
            $text .= (string) $run->children($namespace)->t;
        }

        return $text;
    }

    private function columnIndex(string $cellReference): int
    {
        preg_match('/^[A-Z]+/', strtoupper($cellReference), $matches);
        $index = 0;
        foreach (str_split($matches[0] ?? 'A') as $letter) {
            $index = ($index * 26) + ord($letter) - 64;
        }

        return $index - 1;
    }
}
