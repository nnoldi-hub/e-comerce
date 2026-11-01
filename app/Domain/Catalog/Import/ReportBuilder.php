<?php

namespace App\Domain\Catalog\Import;

/** Raportul de import ca CSV (UTF-8 cu BOM, separator „;”): rând, SKU, nivel, mesaj. */
final class ReportBuilder
{
    /** @param list<ImportIssue> $issues */
    public function csv(array $issues): string
    {
        $stream = fopen('php://temp', 'r+');

        if ($stream === false) {
            return '';
        }

        fputcsv($stream, ['Rând', 'SKU', 'Nivel', 'Mesaj'], ';', '"', '');

        foreach ($issues as $issue) {
            fputcsv($stream, [$issue->row, $issue->sku, $issue->level, $issue->message], ';', '"', '');
        }

        rewind($stream);
        $content = (string) stream_get_contents($stream);
        fclose($stream);

        return "\xEF\xBB\xBF".$content;
    }
}
