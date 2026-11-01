<?php

namespace App\Domain\Catalog\Import;

use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

/**
 * Citește un fișier .xlsx sau .csv în rânduri de text. Tipul se recunoaște după
 * conținut („PK” = arhivă zip = xlsx), nu după extensie sau după tipul MIME trimis de browser.
 */
final class SpreadsheetReader
{
    public const MAX_ROWS = 2000;

    private const MAX_COLUMNS = 60;

    /** Limita de dimensiune a XML-urilor decomprimate (protecție împotriva arhivelor „bomb”). */
    private const MAX_XML_BYTES = 30 * 1024 * 1024;

    private const PREFERRED_SHEET = 'Produse';

    /**
     * @return list<list<string>> rânduri nevide; primul = antetul
     */
    public function read(string $path): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            throw new RuntimeException('Fișierul nu poate fi citit.');
        }

        $head = (string) file_get_contents($path, false, null, 0, 4);

        $rows = str_starts_with($head, "PK\x03\x04") ? $this->readXlsx($path) : $this->readCsv($path);

        return $this->clean($rows);
    }

    /**
     * @param  list<list<string>>  $rows
     * @return list<list<string>>
     */
    private function clean(array $rows): array
    {
        $kept = [];

        foreach ($rows as $row) {
            $row = array_slice($row, 0, self::MAX_COLUMNS);

            while ($row !== [] && trim((string) end($row)) === '') {
                array_pop($row);
            }

            if ($row !== []) {
                $kept[] = array_map(fn ($cell): string => trim((string) $cell), $row);
            }

            if (count($kept) > self::MAX_ROWS + 1) {
                throw new RuntimeException('Fișierul are mai mult de '.self::MAX_ROWS.' de rânduri. Împarte-l în mai multe fișiere.');
            }
        }

        return $kept;
    }

    /** @return list<list<string>> */
    private function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);

        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        if (! mb_check_encoding($content, 'UTF-8')) {
            // Excel în română salvează adesea CSV în Windows-1250.
            $converted = function_exists('iconv') ? @iconv('Windows-1250', 'UTF-8//IGNORE', $content) : false;
            $content = $converted !== false ? $converted : mb_convert_encoding($content, 'UTF-8', 'ISO-8859-2');
        }

        $firstLine = strtok($content, "\n") ?: '';
        $delimiter = ';';
        $best = -1;

        foreach ([';', ',', "\t"] as $candidate) {
            $count = substr_count($firstLine, $candidate);

            if ($count > $best) {
                $best = $count;
                $delimiter = $candidate;
            }
        }

        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            throw new RuntimeException('Fișierul CSV nu poate fi procesat.');
        }

        fwrite($stream, $content);
        rewind($stream);

        $rows = [];

        while (($line = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
            $rows[] = array_map(fn ($cell): string => (string) $cell, $line);

            if (count($rows) > self::MAX_ROWS + 50) {
                break;
            }
        }

        fclose($stream);

        return $rows;
    }

    /** @return list<list<string>> */
    private function readXlsx(string $path): array
    {
        $zip = new ZipArchive;

        if ($zip->open($path) !== true) {
            throw new RuntimeException('Fișierul Excel nu poate fi deschis. Salvează-l ca .xlsx și încearcă din nou.');
        }

        try {
            $workbook = $this->xml($zip, 'xl/workbook.xml');
            $relations = $this->xml($zip, 'xl/_rels/workbook.xml.rels');

            $targets = [];

            foreach ($relations->Relationship ?? [] as $rel) {
                $targets[(string) $rel['Id']] = (string) $rel['Target'];
            }

            $chosen = null;

            foreach ($workbook->sheets->sheet ?? [] as $sheet) {
                $id = (string) ($sheet->attributes('r', true)['id'] ?? '');
                $target = $targets[$id] ?? null;

                if ($target === null) {
                    continue;
                }

                if ($chosen === null || (string) $sheet['name'] === self::PREFERRED_SHEET) {
                    $chosen = $target;
                }

                if ((string) $sheet['name'] === self::PREFERRED_SHEET) {
                    break;
                }
            }

            if ($chosen === null) {
                throw new RuntimeException('Fișierul Excel nu conține nicio foaie.');
            }

            $sheetPath = str_starts_with($chosen, '/') ? ltrim($chosen, '/') : 'xl/'.$chosen;

            return $this->parseSheet($this->xml($zip, $sheetPath), $this->sharedStrings($zip));
        } finally {
            $zip->close();
        }
    }

    private function xml(ZipArchive $zip, string $name): SimpleXMLElement
    {
        $stat = $zip->statName($name);

        if ($stat === false) {
            throw new RuntimeException('Fișierul Excel este incomplet ('.$name.' lipsește).');
        }

        if ($stat['size'] > self::MAX_XML_BYTES) {
            throw new RuntimeException('Fișierul Excel este prea mare.');
        }

        // Fără namespace-ul implicit, elementele se accesează direct ($sheet->sheetData->row);
        // prefixul `r:` (ex. r:id) rămâne declarat și se citește cu attributes('r', true).
        $raw = (string) preg_replace('/\sxmlns="[^"]*"/', '', (string) $zip->getFromName($name), 1);
        $xml = simplexml_load_string($raw, SimpleXMLElement::class, LIBXML_NONET | LIBXML_NOCDATA);

        if ($xml === false) {
            throw new RuntimeException('Fișierul Excel este deteriorat.');
        }

        return $xml;
    }

    /** @return list<string> */
    private function sharedStrings(ZipArchive $zip): array
    {
        if ($zip->statName('xl/sharedStrings.xml') === false) {
            return [];
        }

        $xml = $this->xml($zip, 'xl/sharedStrings.xml');
        $strings = [];

        foreach ($xml->si ?? [] as $si) {
            $strings[] = $this->textOf($si);
        }

        return $strings;
    }

    /** Textul unui <si>/<is>: fie <t>, fie fragmente <r><t> (text formatat). */
    private function textOf(SimpleXMLElement $node): string
    {
        $text = '';

        if (isset($node->t)) {
            $text .= (string) $node->t;
        }

        foreach ($node->r ?? [] as $run) {
            $text .= (string) $run->t;
        }

        return $text;
    }

    /**
     * @param  list<string>  $shared
     * @return list<list<string>>
     */
    private function parseSheet(SimpleXMLElement $sheet, array $shared): array
    {
        $rows = [];

        foreach ($sheet->sheetData->row ?? [] as $row) {
            $cells = [];
            $position = 0;

            foreach ($row->c ?? [] as $cell) {
                $ref = (string) $cell['r'];
                $index = $ref !== '' ? $this->columnIndex($ref) : $position;
                $position = $index + 1;

                if ($index >= self::MAX_COLUMNS) {
                    continue;
                }

                $type = (string) $cell['t'];

                $value = match ($type) {
                    's' => $shared[(int) $cell->v] ?? '',
                    'inlineStr' => isset($cell->is) ? $this->textOf($cell->is) : '',
                    'b' => ((string) $cell->v) === '1' ? 'da' : 'nu',
                    default => (string) $cell->v,
                };

                $cells[$index] = $value;
            }

            if ($cells === []) {
                $rows[] = [];

                continue;
            }

            $line = array_fill(0, max(array_keys($cells)) + 1, '');

            foreach ($cells as $i => $value) {
                $line[$i] = $value;
            }

            $rows[] = $line;

            if (count($rows) > self::MAX_ROWS + 200) {
                break;
            }
        }

        return $rows;
    }

    private function columnIndex(string $ref): int
    {
        preg_match('/^[A-Za-z]+/', $ref, $m);
        $letters = strtoupper($m[0] ?? 'A');
        $n = 0;

        foreach (str_split($letters) as $char) {
            $n = $n * 26 + (ord($char) - 64);
        }

        return $n - 1;
    }
}
