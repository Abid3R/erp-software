<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reusable CSV import/export table actions for master data (spec #37).
 * Export streams the company-scoped rows synchronously; import upserts row-by-row
 * with a per-row handler that returns 'created' | 'updated' | 'skipped'. Company
 * scoping is inherited from the model, so a tenant can only touch its own data.
 */
final class CsvActions
{
    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<string, string|callable(\Illuminate\Database\Eloquent\Model): string>  $columns  header => attribute|closure
     */
    public static function export(string $model, string $filename, array $columns): Action
    {
        return Action::make('export')
            ->label('Export CSV')->icon('heroicon-o-arrow-down-tray')->color('gray')
            ->action(function () use ($model, $filename, $columns): StreamedResponse {
                return response()->streamDownload(function () use ($model, $columns): void {
                    $out = fopen('php://output', 'w');
                    fputcsv($out, array_keys($columns));
                    foreach ($model::query()->cursor() as $record) {
                        fputcsv($out, array_map(
                            fn ($col): string => is_callable($col) ? $col($record) : (string) ($record->{$col} ?? ''),
                            array_values($columns),
                        ));
                    }
                    fclose($out);
                }, $filename);
            });
    }

    /** @param callable(array<string, string>): string $handler */
    public static function import(callable $handler): Action
    {
        return Action::make('import')
            ->label('Import CSV')->icon('heroicon-o-arrow-up-tray')->color('gray')
            ->form([
                FileUpload::make('file')->label('CSV file')->disk('local')->directory('csv-imports')
                    ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])->required(),
            ])
            ->action(function (array $data) use ($handler): void {
                $stored = (string) $data['file'];
                $result = self::process(Storage::disk('local')->path($stored), $handler);
                Storage::disk('local')->delete($stored);

                Notification::make()
                    ->title('Import complete')
                    ->body("Created {$result['created']}, updated {$result['updated']}, skipped {$result['skipped']}.")
                    ->success()->send();
            });
    }

    /**
     * @param  callable(array<string, string>): string  $handler
     * @return array{created: int, updated: int, skipped: int}
     */
    public static function process(string $path, callable $handler): array
    {
        $counts = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        $handle = fopen($path, 'r');

        if ($handle === false) {
            return $counts;
        }

        $header = fgetcsv($handle);
        if ($header === false) {
            fclose($handle);

            return $counts;
        }
        $header = array_map(fn ($h): string => trim((string) $h), $header);

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) !== count($header)) {
                $counts['skipped']++;

                continue;
            }

            try {
                $outcome = $handler(array_combine($header, array_map(fn ($v): string => trim((string) $v), $row)));
                $counts[$outcome] = ($counts[$outcome] ?? 0) + 1;
            } catch (\Throwable) {
                $counts['skipped']++;
            }
        }

        fclose($handle);

        return $counts;
    }

    /** Parse a truthy CSV cell (1/yes/true/y). */
    public static function bool(string $value): bool
    {
        return in_array(strtolower(trim($value)), ['1', 'yes', 'true', 'y'], true);
    }
}
