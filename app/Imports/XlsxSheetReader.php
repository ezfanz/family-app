<?php

namespace App\Imports;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Minimal reader for one worksheet of an .xlsx file using PHP's built-in zip
 * and XML support (no spreadsheet package). Handles shared strings, inline
 * strings, formula results, numbers and booleans, which is everything the
 * shopping list template uses, including after a re-save in Excel or Sheets.
 */
class XlsxSheetReader
{
    /**
     * Biggest uncompressed XML part we will read (guards against zip bombs).
     */
    private const MAX_PART_BYTES = 8 * 1024 * 1024;

    private const NS_MAIN = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';

    private const NS_REL = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';

    private ZipArchive $zip;

    private bool $opened = false;

    /**
     * @var list<string>
     */
    private array $sharedStrings = [];

    public function __construct(string $path)
    {
        $this->zip = new ZipArchive;
        if ($this->zip->open($path, ZipArchive::RDONLY) !== true) {
            throw new RuntimeException('This file is not a valid Excel (.xlsx) file.');
        }
        $this->opened = true;
        if ($this->zip->locateName('xl/workbook.xml') === false) {
            throw new RuntimeException('This file is not a valid Excel (.xlsx) file.');
        }
        $this->loadSharedStrings();
    }

    /**
     * Rows of the named sheet (or the first sheet), each as column letter => text.
     *
     * @return array<int, array<string, string>> keyed by Excel row number
     */
    public function rows(?string $preferredSheet = null, int $maxRows = 600): array
    {
        $xml = $this->xml($this->sheetPath($preferredSheet));
        $xml->registerXPathNamespace('m', self::NS_MAIN);

        $rows = [];
        foreach ($xml->xpath('//m:sheetData/m:row') ?: [] as $row) {
            $number = (int) $row->attributes()['r'];
            if ($number > $maxRows) {
                throw new RuntimeException("The sheet has more than {$maxRows} rows. Split it into smaller files.");
            }
            $cells = [];
            foreach ($row->children(self::NS_MAIN)->c as $cell) {
                $column = preg_replace('/\d+/', '', (string) $cell->attributes()['r']);
                $value = $this->cellValue($cell);
                if ($column !== '' && $value !== '') {
                    $cells[$column] = $value;
                }
            }
            if ($cells !== []) {
                $rows[$number] = $cells;
            }
        }

        return $rows;
    }

    private function cellValue(SimpleXMLElement $cell): string
    {
        $type = (string) $cell->attributes()['t'];
        $children = $cell->children(self::NS_MAIN);

        if ($type === 'inlineStr') {
            return trim($this->textOf($children->is));
        }

        $raw = isset($children->v) ? (string) $children->v : '';
        if ($raw === '') {
            return '';
        }

        return trim(match ($type) {
            's' => $this->sharedStrings[(int) $raw] ?? '',
            'b' => $raw === '1' ? 'TRUE' : 'FALSE',
            default => $raw,
        });
    }

    /**
     * Text of a string item, joining rich-text runs.
     */
    private function textOf(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        $node->registerXPathNamespace('m', self::NS_MAIN);
        $parts = $node->xpath('.//m:t') ?: [];

        return implode('', array_map('strval', $parts));
    }

    private function loadSharedStrings(): void
    {
        if ($this->zip->locateName('xl/sharedStrings.xml') === false) {
            return;
        }
        $xml = $this->xml('xl/sharedStrings.xml');
        foreach ($xml->children(self::NS_MAIN)->si as $item) {
            $this->sharedStrings[] = $this->textOf($item);
        }
    }

    private function sheetPath(?string $preferred): string
    {
        $workbook = $this->xml('xl/workbook.xml');
        $sheets = $workbook->children(self::NS_MAIN)->sheets->sheet ?? [];

        $chosen = null;
        foreach ($sheets as $sheet) {
            $chosen ??= $sheet;
            if ($preferred !== null && strcasecmp(trim((string) $sheet->attributes()['name']), $preferred) === 0) {
                $chosen = $sheet;
                break;
            }
        }
        if ($chosen === null) {
            throw new RuntimeException('The file has no sheets.');
        }

        $relationId = (string) $chosen->attributes(self::NS_REL)['id'];
        $rels = $this->xml('xl/_rels/workbook.xml.rels');
        foreach ($rels->children('http://schemas.openxmlformats.org/package/2006/relationships')->Relationship as $rel) {
            if ((string) $rel->attributes()['Id'] === $relationId) {
                $target = ltrim((string) $rel->attributes()['Target'], '/');

                return str_starts_with($target, 'xl/') ? $target : 'xl/'.$target;
            }
        }

        throw new RuntimeException('Could not find the sheet inside the file.');
    }

    private function xml(string $part): SimpleXMLElement
    {
        $stat = $this->zip->statName($part);
        if ($stat === false) {
            throw new RuntimeException('The file is missing part of the workbook.');
        }
        if ($stat['size'] > self::MAX_PART_BYTES) {
            throw new RuntimeException('The file is too large to import.');
        }

        $content = $this->zip->getFromName($part);
        $previous = libxml_use_internal_errors(true);
        $xml = simplexml_load_string((string) $content, SimpleXMLElement::class, LIBXML_NONET);
        libxml_use_internal_errors($previous);

        if ($xml === false) {
            throw new RuntimeException('The file could not be read. Save it again as .xlsx and retry.');
        }

        return $xml;
    }

    public function __destruct()
    {
        if ($this->opened) {
            $this->zip->close();
        }
    }
}
