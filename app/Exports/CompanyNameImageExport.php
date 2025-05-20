<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;
use App\Models\Company;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use Override;

class CompanyNameImageExport implements FromCollection, WithEvents
{
    /**
     * @return Collection<int, Company>
     */
    #[Override]
    public function collection(): Collection
    {
        return Company::where('status', COMPANY_STATUS_ACTIVE)->select('name', 'logo')->get();
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event): void {
                $companies = Company::where('status', COMPANY_STATUS_ACTIVE)->select('name', 'logo')->get();

                foreach ($companies as $index => $company) {
                    $event->sheet->setCellValue('A'.($index + 2), $company->name);
                    $event->sheet->setCellValue('B'.($index + 2), '');
                    if ($company->logo) {
                        $imagePath = Storage::disk('public')->path(Str::replace('/storage/', '', $company->logo));
                        // $imagePath = asset($company->logo);
                        // $imageContents = file_get_contents($imagePath);
                        // $imageData = base64_encode($imageContents);

                        $event->sheet->getRowDimension($index + 2)->setRowHeight(100);
                        $event->sheet->getDelegate()->setCellValue('B'.($index + 2), '');
                        $event->sheet->getDelegate()->getRowDimension($index + 2)->setRowHeight(100);
                        $drawing = new Drawing;
                        $drawing->setName($company->name);
                        $drawing->setDescription($company->name);
                        $drawing->setPath($imagePath);
                        $drawing->setCoordinates('B'.($index + 2));
                        $drawing->setHeight(100);
                        $drawing->setWorksheet($event->sheet->getDelegate());
                    }
                }
            },
        ];
    }
}
