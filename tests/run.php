<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Core\TestCase;
use App\Core\HttpTestCase;

function discoverTests(string $directory): array
{
    $files = [];
    foreach (glob($directory . '/*') as $fileOrDir) {
        if (is_dir($fileOrDir)) {
            $files = array_merge($files, discoverTests($fileOrDir));
        } elseif (str_ends_with($fileOrDir, '.php')) {
            $files[] = $fileOrDir;
        }
    }
    return $files;
}

$testFiles = discoverTests(__DIR__);

$totalAssertions = 0;
$totalFailures = 0;

foreach ($testFiles as $file) {
    require $file;

    $classes = get_declared_classes();
    $className = end($classes);

    $testInstance = new $className();

    if ($testInstance instanceof TestCase) {
        echo "Running " . $className . "...\n";
        $testInstance->run();
        $totalAssertions += $testInstance->assertions;
        $totalFailures += $testInstance->failures;
    }
}

echo "\n====== Test Summary ======\n";
echo "Total Assertions: $totalAssertions\n";
echo "Total Failures: $totalFailures\n";

exit($totalFailures > 0 ? 1 : 0);
