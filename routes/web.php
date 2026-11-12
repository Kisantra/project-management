<?php

use Illuminate\Support\Facades\Route;
use App\Filament\Pages\ProjectDetails;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/welcome', function () {
    return view('welcome');
});

Route::get('/download/pph-example', function () {
    $examplePath = storage_path('app/examples/IncomeTaxExample.xlsx');
    
    if (file_exists($examplePath)) {
        return response()->download($examplePath, 'Contoh_Import_PPh.xlsx');
    }
    
    abort(404, 'File contoh tidak ditemukan');
})->name('download.pph.example')->middleware('auth');

// Route::get('/admin/projects/{record}', ProjectDetails::class)->name('filament.pages.project_details');

// Route::get('/storage/download-all', [App\Http\Controllers\StorageDownloadController::class, 'downloadAll'])
//     ->name('storage.download-all')
//     ->middleware(['auth', 'verified']);

// The Project Dashboard and Dashboard Pajak pages were merged into the main
// dashboard (2026-11). Keep old bookmarks working.
Route::redirect('/project-dashboard', '/');
Route::redirect('/dashboard-tax-report', '/');
