<?php
namespace Services\Reports;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ExcelReport implements ReportInterface {
    public function generate(array $data, string $filePath): string {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $rowIndex = 1;
        foreach ($data as $row) {
            $colIndex = 1;
            foreach ($row as $cell) {
                $sheet->setCellValueByColumnAndRow($colIndex++, $rowIndex, $cell);
            }
            $rowIndex++;
        }
        $writer = new Xlsx($spreadsheet);
        $writer->save($filePath);
        return $filePath;
    }
}
