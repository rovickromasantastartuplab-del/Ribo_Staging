<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $tempFile = tempnam(sys_get_temp_dir(), 'test');
    file_put_contents($tempFile, "name,email\nJohn,john@example.com");

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($tempFile);
    $worksheet = $spreadsheet->getActiveSheet();
    $highestColumn = $worksheet->getHighestColumn();
    $highestRow = $worksheet->getHighestRow();

    echo "Highest Col: $highestColumn, Highest Row: $highestRow\n";

    $headers = [];
    for ($col = 'A'; $col <= $highestColumn; $col++) {
        $value = $worksheet->getCell($col . '1')->getValue();
        if ($value) {
            $headers[] = $value;
        }
    }
    dump($headers);
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo $e->getTraceAsString();
}
