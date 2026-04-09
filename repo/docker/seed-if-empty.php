<?php

declare(strict_types=1);

/**
 * Runs DatabaseSeeder only when the roles table exists and has no rows.
 * Avoids duplicate-key failures on container restarts (seeders use plain insert).
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

if (! Schema::hasTable('roles')) {
    exit(0);
}

if ((int) DB::table('roles')->count() === 0) {
    Artisan::call('db:seed', ['--force' => true]);
}
