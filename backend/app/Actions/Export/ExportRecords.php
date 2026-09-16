<?php

namespace App\Actions\Export;

use App\Exports\QueryExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportRecords
{
    /**
     * A PDF lays out its whole document before rendering — unlike CSV/XLSX
     * it can't stream row by row, so it's capped to a sane page count
     * rather than ever loading an unbounded result set.
     */
    private const PDF_ROW_LIMIT = 1000;

    /**
     * @param  array<string, string>  $columns  attribute/accessor => column heading
     */
    public function stream(Builder $query, array $columns, string $format, string $filename): Response
    {
        return match ($format) {
            'xlsx' => (new QueryExport($query, $columns))->download("{$filename}.xlsx"),
            'pdf' => $this->streamPdf($query, $columns, $filename),
            default => $this->streamCsv($query, $columns, $filename),
        };
    }

    /**
     * Backed enums (e.g. a model's status cast) render fine through
     * Blade's {{ }} — it unwraps them itself — but PhpSpreadsheet's cell
     * writer and fputcsv both reject them outright ("unable to bind
     * unstringable object" / uncatchable TypeError), so every non-Blade
     * export path needs this unwrap explicitly.
     */
    public static function formatValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }

    /**
     * fputcsv over a lazy cursor — genuinely constant memory regardless
     * of row count, never materializing the full result set at once.
     */
    private function streamCsv(Builder $query, array $columns, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($query, $columns) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, array_values($columns));

            foreach ($query->cursor() as $row) {
                fputcsv($handle, array_map(
                    fn (string $attribute) => self::formatValue(data_get($row, $attribute)),
                    array_keys($columns)
                ));
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.csv\"");

        return $response;
    }

    private function streamPdf(Builder $query, array $columns, string $filename): StreamedResponse
    {
        $rows = $query->limit(self::PDF_ROW_LIMIT)->get();

        $pdf = Pdf::loadView('exports.table', ['title' => $filename, 'columns' => $columns, 'rows' => $rows])
            ->setPaper('a4', 'landscape');

        $response = new StreamedResponse(function () use ($pdf) {
            echo $pdf->output();
        });
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.pdf\"");

        return $response;
    }
}
