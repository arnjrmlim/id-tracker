<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class IdRecordTemplateExport implements FromArray, WithHeadings, WithStyles, ShouldAutoSize
{
    public function headings(): array
    {
        return ['NAME', 'POS', 'IDNO', 'DATEH', 'BDATE', 'ECON', 'IMG', 'SIGN', 'EMPLOYMENT TYPE'];
    }

    public function array(): array
    {
        // One example row to guide users; can be deleted before importing
        return [
            [
                'Juan Dela Cruz',
                'Staff',
                '100001',
                '01/15/2020',
                '05/10/1990',
                'Maria Cruz: 09171234567',
                'Z:\path\to\id_image.png',
                'Z:\path\to\signature.png',
                'Employee',
            ],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']], 'fill' => ['fillType' => 'solid', 'color' => ['argb' => 'FF1a3c5e']]],
        ];
    }
}
