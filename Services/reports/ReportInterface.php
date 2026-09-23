<?php
namespace Services\Reports;

interface ReportInterface {
    public function generate(array $data, string $filePath): string;
}
