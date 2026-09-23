<?php

namespace App\Actions\Export;

use App\Exports\TimesheetExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Same three formats as ExportRecords, but for the timesheet's grid shape
 * (BuildTimesheet's output) rather than a flat Eloquent query — a
 * dedicated action instead of extending ExportRecords, since neither the
 * CSV writer nor the PDF view here work off a query cursor at all.
 */
class ExportTimesheet
{
    /**
     * A whole month's employees × days is small enough to lay out in one
     * PDF page set without a row limit the way ExportRecords needs for an
     * unbounded query — this is already capped by how many employees the
     * request is scoped to.
     */
    public function stream(array $timesheet, string $format, string $filename): Response
    {
        return match ($format) {
            'xlsx' => (new TimesheetExport($timesheet))->download("{$filename}.xlsx"),
            'pdf' => $this->streamPdf($timesheet, $filename),
            default => $this->streamCsv($timesheet, $filename),
        };
    }

    private function streamCsv(array $timesheet, string $filename): StreamedResponse
    {
        $response = new StreamedResponse(function () use ($timesheet) {
            $handle = fopen('php://output', 'w');
            $export = new TimesheetExport($timesheet);

            fputcsv($handle, $export->headings());

            foreach ($export->array() as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        });

        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.csv\"");

        return $response;
    }

    private function streamPdf(array $timesheet, string $filename): StreamedResponse
    {
        $pdf = Pdf::loadView('exports.timesheet', ['title' => $filename, 'timesheet' => $timesheet])
            ->setPaper('a3', 'landscape');

        $response = new StreamedResponse(function () use ($pdf) {
            echo $pdf->output();
        });
        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', "attachment; filename=\"{$filename}.pdf\"");

        return $response;
    }
}
