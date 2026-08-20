<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a CSV download. Small, dependency-free helper shared by the report
 * pages so every "Export CSV" button behaves identically (UTF-8 BOM so Excel
 * renders the ৳ symbol and Bangla text correctly).
 */
final class CsvExport
{
    /**
     * @param  list<string>  $headers
     * @param  iterable<array<int, string|int|float|null>>  $rows
     */
    public static function stream(array $headers, iterable $rows, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');

            // UTF-8 BOM — makes Excel honour multibyte characters (৳, Bangla).
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);
            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
