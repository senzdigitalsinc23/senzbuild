<?php
namespace Services\Reports;

use Services\Reports\ReportInterface;
use TCPDF;

class PdfReport implements ReportInterface {
    public function generate(array $data, string $filePath): string {
        $pdf = new TCPDF();
        $pdf->AddPage();
        $html = "<h1>Report</h1><table border='1' class='table-bordered'>";
        foreach ($data as $row) {
            $html .= "<tr><td>".implode("</td><td>", $row)."</td></tr>";
        }
        $html .= "</table>";
        $pdf->writeHTML($html);
        $pdf->Output($filePath, 'F');
        return $filePath;
    }
}
