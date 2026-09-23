<?php
namespace Services\Reports;

use Services\Reports\PdfReport;
use Services\Reports\ReportInterface;

class ReportFactory {
    public static function create(string $type): ReportInterface {
        return match (strtolower($type)) {
            'pdf'   => new PdfReport(),
            'excel' => new ExcelReport(),
            default => response()->json(['success' => false, 'message' => 'Usupported file type'], 201)
        };
    }
}
