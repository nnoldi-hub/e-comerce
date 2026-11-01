<?php

namespace App\Domain\Catalog\Import;

use RuntimeException;
use ZipArchive;

/**
 * Scrie un registru .xlsx simplu (text și numere, prima linie ca antet îngroșat),
 * fără biblioteci externe. Textul se salvează ca „inline string”, deci un SKU ca
 * `00123` rămâne text, iar Excel nu îl transformă în număr.
 */
final class XlsxWriter
{
    /**
     * @param  array<string, list<list<scalar|null>>>  $sheets  nume foaie => rânduri (primul rând = antet)
     * @param  array<string, list<int>>  $widths  nume foaie => lățimi de coloană (în caractere)
     */
    public function write(string $path, array $sheets, array $widths = []): void
    {
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Nu se poate crea fișierul Excel.');
        }

        $names = array_keys($sheets);
        $overrides = '';
        $sheetEntries = '';
        $relEntries = '';

        foreach ($names as $index => $name) {
            $n = $index + 1;
            $overrides .= '<Override PartName="/xl/worksheets/sheet'.$n.'.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $sheetEntries .= '<sheet name="'.$this->esc($this->sheetName($name)).'" sheetId="'.$n.'" r:id="rId'.$n.'"/>';
            $relEntries .= '<Relationship Id="rId'.$n.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet'.$n.'.xml"/>';

            $zip->addFromString('xl/worksheets/sheet'.$n.'.xml', $this->sheetXml($sheets[$name], $widths[$name] ?? []));
        }

        $stylesRel = count($names) + 1;

        $zip->addFromString('[Content_Types].xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .$overrides.'</Types>');

        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            .'<sheets>'.$sheetEntries.'</sheets></workbook>');

        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'.$relEntries
            .'<Relationship Id="rId'.$stylesRel.'" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            .'</Relationships>');

        $zip->addFromString('xl/styles.xml', $this->stylesXml());

        $zip->close();
    }

    /**
     * @param  list<list<scalar|null>>  $rows
     * @param  list<int>  $widths
     */
    private function sheetXml(array $rows, array $widths): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';

        if ($widths !== []) {
            $xml .= '<cols>';

            foreach (array_values($widths) as $i => $width) {
                $xml .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.$width.'" customWidth="1"/>';
            }

            $xml .= '</cols>';
        }

        $xml .= '<sheetData>';

        foreach (array_values($rows) as $r => $row) {
            $xml .= '<row r="'.($r + 1).'">';

            foreach (array_values($row) as $c => $value) {
                if ($value === null || $value === '') {
                    continue;
                }

                $ref = $this->columnLetters($c).($r + 1);
                $style = $r === 0 ? ' s="1"' : '';

                if (is_int($value) || is_float($value)) {
                    $xml .= '<c r="'.$ref.'"'.$style.'><v>'.$value.'</v></c>';
                } else {
                    $xml .= '<c r="'.$ref.'" t="inlineStr"'.$style.'><is><t xml:space="preserve">'.$this->esc((string) $value).'</t></is></c>';
                }
            }

            $xml .= '</row>';
        }

        return $xml.'</sheetData></worksheet>';
    }

    private function stylesXml(): string
    {
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            .'<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="3"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            .'<fill><patternFill patternType="solid"><fgColor rgb="FFDBEAFE"/><bgColor indexed="64"/></patternFill></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            .'<xf numFmtId="0" fontId="1" fillId="2" borderId="0" xfId="0" applyFont="1" applyFill="1"/></cellXfs>'
            .'</styleSheet>';
    }

    private function columnLetters(int $index): string
    {
        $letters = '';
        $n = $index + 1;

        while ($n > 0) {
            $mod = ($n - 1) % 26;
            $letters = chr(65 + $mod).$letters;
            $n = intdiv($n - 1, 26);
        }

        return $letters;
    }

    private function sheetName(string $name): string
    {
        return mb_substr(str_replace(['\\', '/', '?', '*', '[', ']', ':'], ' ', $name), 0, 31);
    }

    private function esc(string $text): string
    {
        // Caracterele de control (în afară de tab / linie nouă) nu sunt permise în XML 1.0.
        $clean = (string) preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]/u', '', $text);

        return htmlspecialchars($clean, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
