<?php
namespace App\Services\Reports;

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;

class WordReport implements ReportInterface {
    public function generate(array $data, string $filePath): string {
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $section->addText("Report");
        foreach ($data as $row) {
            $section->addText(implode(" | ", $row));
        }
        $writer = IOFactory::createWriter($phpWord, 'Word2007');
        $writer->save($filePath);
        return $filePath;
    }
}
