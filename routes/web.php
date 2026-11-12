<?php

use Illuminate\Support\Facades\Route;
use App\Services\DevSentinelService;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/test-sentinel', function (DevSentinelService $sentinel) {
    try {
        // Intentionally triggering an undefined variable calculation error
        $salary = 50000;
        $bonus = $undefinedBonusRate * 10;
        $total = $salary + $bonus;
        return $total;
    } catch (\Throwable $e) {
        $diagnosis = $sentinel->diagnose($e);
        return response()->json($diagnosis, 200, [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
});

