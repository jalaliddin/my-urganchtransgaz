<?php

namespace App\Exports;

use App\Actions\Export\ExportRecords;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * A generic .xlsx export for any Eloquent query — the query itself
 * decides scope/filters, this class only knows how to lay out columns.
 * FromQuery lets the package chunk the result set internally instead of
 * loading it all into memory at once.
 */
class QueryExport implements FromQuery, WithHeadings, WithMapping
{
    use Exportable;

    /**
     * @param  array<string, string>  $columns  attribute/accessor => column heading
     */
    public function __construct(private Builder $query, private array $columns)
    {
        //
    }

    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return array<int, string>
     */
    public function headings(): array
    {
        return array_values($this->columns);
    }

    /**
     * @return array<int, mixed>
     */
    public function map($row): array
    {
        return array_map(
            fn (string $attribute) => ExportRecords::formatValue(data_get($row, $attribute)),
            array_keys($this->columns)
        );
    }
}
