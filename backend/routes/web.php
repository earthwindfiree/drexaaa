<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json([
        'message' => 'Managed Trading Demo Platform API is ready.',
        'status' => 'ok',
    ]);
});
