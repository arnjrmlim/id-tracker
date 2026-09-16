<?php

namespace App\Exports;

use App\Models\IdRecord;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IdRecordExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    public function __construct(
        private readonly bool    $includeStatus = false,
        private readonly ?string $status        = null,
        private readonly ?array  $ids           = null,
        private readonly ?string $search        = null,
    ) {}

    public function query(): Builder
    {
        $query = IdRecord::query();

        if ($this->ids) {
            $query->whereIn('id', $this->ids);
        }

        if ($this->status) {
            $query->where('status', $this->status);
        }

        if ($this->search) {
            $query->search($this->search);
        }

        return $query->orderBy('name');
    }

    public function headings(): array
    {
        $headers = ['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN'];

        if ($this->includeStatus) {
            $headers[] = 'STATUS';
        }

        return $headers;
    }

    public function map($record): array
    {
        $row = [
            $record->name,
            $record->position,
            $record->id_number,
            $record->date_hired ? $record->date_hired->format('m/d/Y') : '',
            $record->birth_date ? $record->birth_date->format('m/d/Y') : '',
            $record->emergency_contact,
            $record->image_path,
            $record->signature_path,
        ];

        if ($this->includeStatus) {
            $row[] = $record->status;
        }

        return $row;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
