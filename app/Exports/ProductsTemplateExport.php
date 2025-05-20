<?php

namespace App\Exports;

use App\Models\ProductCategory;
use App\Models\ProductUnit;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Events\AfterSheet;
use Override;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

class ProductsTemplateExport implements FromCollection, WithEvents, WithHeadings
{
    /**
     * @var array<int, ProductUnit>
     */
    protected $productUnits;

    /**
     * @var array<int, ProductCategory>
     */
    protected $productCategories;

    /**
     * @var array<int, string>
     */
    protected $isTaxables = ['Yes', 'No'];

    /**
     * @var array<mixed>
     */
    protected array $selects;

    /**
     * @var int
     */
    protected $row_count = 301;

    /**
     * @var int
     */
    protected $column_count = 6;

    public function __construct()
    {
        $this->productUnits = auth()->guard('api')->user()->company->productUnits()->pluck('name')->toArray();
        $this->productCategories = auth()->guard('api')->user()->company->productCategories()->pluck('name')->toArray();
        $selects = [  //selects should have column_name and options
            ['columns_name' => 'D', 'options' => $this->productCategories],
            ['columns_name' => 'E', 'options' => $this->productUnits],
            ['columns_name' => 'F', 'options' => $this->isTaxables],
        ];
        $this->selects = $selects; //number of columns to be auto sized
    }

    /**
     * @return Collection<int, mixed>
     */
    #[Override]
    public function collection(): Collection
    {
        return collect([]);
    }

    /**
     * @return array<int, string>
     */
    #[Override]
    public function headings(): array
    {
        return [
            'NAME IN ARABIC',
            'NAME IN ENGLISH',
            'PRICE',
            'CATEGORY',
            'UNIT',
            'IS TAXABLE',
        ];
    }

    /**
     * @return array<mixed>
     */
    #[Override]
    public function registerEvents(): array
    {
        return [
            // handle by a closure.
            AfterSheet::class => function (AfterSheet $event): void {
                $row_count = $this->row_count;
                $column_count = $this->column_count;
                foreach ($this->selects as $select) {
                    $drop_column = $select['columns_name'];
                    $options = $select['options'];
                    // set dropdown list for first data row
                    $validation = $event->sheet->getCell("{$drop_column}2")->getDataValidation();
                    $validation->setType(DataValidation::TYPE_LIST);
                    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
                    $validation->setAllowBlank(false);
                    $validation->setShowInputMessage(true);
                    $validation->setShowErrorMessage(true);
                    $validation->setShowDropDown(true);
                    $validation->setErrorTitle('Input error');
                    $validation->setError('Value is not in list.');
                    $validation->setPromptTitle('Pick from list');
                    $validation->setPrompt('Please pick a value from the drop-down list.');
                    $validation->setFormula1(sprintf('"%s"', implode(',', $options)));

                    // clone validation to remaining rows
                    for ($i = 3; $i <= $row_count; $i++) {
                        $event->sheet->getCell("{$drop_column}{$i}")->setDataValidation(clone $validation);
                    }
                    // set columns to autosize
                    for ($i = 1; $i <= $column_count; $i++) {
                        $column = Coordinate::stringFromColumnIndex($i);
                        $event->sheet->getColumnDimension($column)->setAutoSize(true);
                    }
                }
            },
        ];
    }
}
