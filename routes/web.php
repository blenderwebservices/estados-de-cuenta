<?php

use Illuminate\Support\Facades\Route;
use App\Models\BankStatement;
use App\Services\ExcelExporter;

Route::get('/', function () {
    return view('welcome');
});

