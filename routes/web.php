<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PersonelController;
use App\Http\Controllers\ProsesController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\SlipController;
use App\Http\Middleware\DemoAuth;
use Illuminate\Support\Facades\Route;

require_once app_path('helpers.php');   // rp(), juta(), terbilang() untuk view

Route::get('/masuk', [AuthController::class, 'create'])->name('masuk');
Route::post('/masuk', [AuthController::class, 'store'])->name('masuk.simpan');

Route::middleware(DemoAuth::class)->group(function () {
    Route::get('/', fn () => redirect()->route('dasbor'));
    Route::post('/keluar', [AuthController::class, 'destroy'])->name('keluar');

    Route::get('/dasbor', [DashboardController::class, 'index'])->name('dasbor');
    Route::post('/periode', [DashboardController::class, 'periode'])->name('periode');

    // Proses gaji bertahap
    Route::get('/proses', [ProsesController::class, 'index'])->name('proses');
    Route::post('/proses/aksi', [ProsesController::class, 'aksi'])->name('proses.aksi');
    Route::get('/proses/{langkah}', [ProsesController::class, 'show'])->whereNumber('langkah')->name('proses.show');

    // Slip gaji
    Route::get('/slip', [SlipController::class, 'index'])->name('slip.index');
    Route::get('/slip/cetak', [SlipController::class, 'cetak'])->name('slip.cetak');
    Route::get('/slip/{n}', [SlipController::class, 'show'])->whereNumber('n')->name('slip.show');

    // Data personel
    Route::get('/personel', [PersonelController::class, 'index'])->name('personel.index');
    Route::get('/personel/baru', [PersonelController::class, 'create'])->name('personel.create');
    Route::post('/personel', [PersonelController::class, 'store'])->name('personel.store');
    Route::get('/personel/impor', [PersonelController::class, 'impor'])->name('personel.impor');
    Route::post('/personel/impor/baca', [PersonelController::class, 'bacaImpor'])->name('personel.impor.baca');
    Route::post('/personel/impor/terapkan', [PersonelController::class, 'terapkanImpor'])->name('personel.impor.terapkan');
    Route::get('/personel/templat', [PersonelController::class, 'templat'])->name('personel.templat');
    Route::get('/personel/{n}/ubah', [PersonelController::class, 'edit'])->whereNumber('n')->name('personel.edit');
    Route::put('/personel/{n}', [PersonelController::class, 'update'])->whereNumber('n')->name('personel.update');

    // Rekap & laporan
    Route::get('/rekap', [RekapController::class, 'index'])->name('rekap.index');
    Route::get('/rekap/{id}', [RekapController::class, 'show'])->name('rekap.show');
    Route::get('/rekap/{id}/unduh', [RekapController::class, 'unduh'])->name('rekap.unduh');

    // Pengaturan
    Route::get('/pengaturan', [PengaturanController::class, 'edit'])->name('pengaturan');
    Route::post('/pengaturan', [PengaturanController::class, 'update'])->name('pengaturan.simpan');
    Route::post('/pengaturan/reset', [PengaturanController::class, 'reset'])->name('pengaturan.reset');
});
