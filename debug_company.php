<?php

require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\Landlord\Company;

// Debug script to check company data
$slug = 'ahmed';
echo "Searching for slug: $slug\n";

$company = Company::on('mysql')->where('slug', $slug)->first();

if ($company) {
    echo "Files found.\n";
    echo "Company Found: {$company->name}\n";
    echo "Slug: {$company->slug}\n";
    echo "Database: '{$company->database}'\n";
    echo "Database Empty?: " . (empty($company->database) ? 'YES' : 'NO') . "\n";
} else {
    echo "Company '{$slug}' NOT FOUND\n";
}
