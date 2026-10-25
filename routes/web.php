<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImporPersonelController;
use App\Http\Controllers\PengaturanController;
use App\Http\Controllers\PersonelController;
use App\Http\Controllers\PotonganController;
use App\Http\Controllers\ProsesGajiController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\SlipController;
use Illuminate\Support\Facades\Route;

require_once app_path('helpers.php');   // rp(), juta(), terbilang() untuk view

Route::middleware('guest')->group(function () {
    Route::get('/masuk', [AuthController::class, 'create'])->name('masuk');
    Route::post('/masuk', [AuthController::class, 'store'])->middleware('throttle:5,1')->name('masuk.simpan');
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => redirect()->route('dasbor'));
    Route::post('/keluar', [AuthController::class, 'destroy'])->name('keluar');

    Route::get('/dasbor', [DashboardController::class, 'index'])->name('dasbor');
    Route::post('/periode', [DashboardController::class, 'periode'])->name('periode');

    // Proses gaji sungguhan (Python di belakangnya)
    Route::get('/gaji', [ProsesGajiController::class, 'index'])->name('gaji.index');
    Route::post('/gaji', [ProsesGajiController::class, 'store'])->name('gaji.store');
    Route::delete('/gaji/{proses}', [ProsesGajiController::class, 'hapus'])->whereNumber('proses')->middleware('can:hapus-proses')->name('gaji.hapus');
    Route::get('/gaji/{proses}', [ProsesGajiController::class, 'show'])->whereNumber('proses')->name('gaji.show');
    Route::get('/gaji/{proses}/langkah/{langkah}', [ProsesGajiController::class, 'langkah'])->whereNumber(['proses', 'langkah'])->name('gaji.langkah');
    Route::get('/gaji/{proses}/unduh/{nama}', [ProsesGajiController::class, 'unduh'])->whereNumber('proses')->name('gaji.unduh');

    // Slip gaji
    Route::get('/slip', [SlipController::class, 'index'])->name('slip.index');
    Route::get('/slip/cetak', [SlipController::class, 'cetak'])->name('slip.cetak');
    Route::get('/slip/{n}', [SlipController::class, 'show'])->whereNumber('n')->name('slip.show');

    // Data personel
    Route::get('/personel', [PersonelController::class, 'index'])->name('personel.index');
    Route::get('/personel/baru', [PersonelController::class, 'create'])->name('personel.create');
    Route::post('/personel', [PersonelController::class, 'store'])->name('personel.store');
    Route::get('/personel/impor', [ImporPersonelController::class, 'index'])->name('personel.impor');
    Route::post('/personel/impor/baru/{jenis}', [ImporPersonelController::class, 'store'])->name('personel.impor.store');
    Route::get('/personel/impor/{impor}', [ImporPersonelController::class, 'show'])->whereNumber('impor')->name('personel.impor.show');
    Route::post('/personel/impor/{impor}/terapkan', [ImporPersonelController::class, 'terapkan'])->whereNumber('impor')->name('personel.impor.terapkan');
    Route::get('/personel/riwayat', [PersonelController::class, 'riwayat'])->name('personel.riwayat');
    Route::get('/personel/{personel}/ubah', [PersonelController::class, 'edit'])->whereNumber('personel')->name('personel.edit');
    Route::put('/personel/{personel}', [PersonelController::class, 'update'])->whereNumber('personel')->name('personel.update');
    Route::delete('/personel/{personel}', [PersonelController::class, 'destroy'])->whereNumber('personel')->middleware('can:hapus-personel')->name('personel.hapus');

    // Potongan satuan (koperasi, pinjaman, arisan, iuran tetap)
    Route::get('/potongan', [PotonganController::class, 'index'])->name('potongan.index');
    Route::get('/potongan/orang/{personel}', [PotonganController::class, 'orang'])->whereNumber('personel')->name('potongan.orang');
    Route::post('/potongan/orang/{personel}', [PotonganController::class, 'simpanOrang'])->whereNumber('personel')->middleware('can:kelola-potongan')->name('potongan.simpan');
    Route::post('/potongan/orang/{personel}/pokok', [PotonganController::class, 'simpanPokok'])->whereNumber('personel')->middleware('can:kelola-potongan')->name('potongan.pokok');
    Route::post('/potongan/pinjaman', [PotonganController::class, 'tambahPinjaman'])->middleware('can:kelola-potongan')->name('potongan.pinjaman.tambah');
    Route::put('/potongan/pinjaman/{pinjaman}', [PotonganController::class, 'ubahPinjaman'])->whereNumber('pinjaman')->middleware('can:kelola-potongan')->name('potongan.pinjaman.ubah');
    Route::post('/potongan/aturan', [PotonganController::class, 'simpanAturan'])->middleware('can:kelola-potongan')->name('potongan.aturan');

    // Rekap & laporan
    Route::get('/rekap', [RekapController::class, 'index'])->name('rekap.index');
    Route::get('/rekap/{id}', [RekapController::class, 'show'])->name('rekap.show');
    Route::get('/rekap/{id}/unduh', [RekapController::class, 'unduh'])->name('rekap.unduh');

    // Pengaturan
    Route::get('/pengaturan', [PengaturanController::class, 'edit'])->name('pengaturan');
    Route::post('/pengaturan', [PengaturanController::class, 'update'])->middleware('can:ubah-pengaturan')->name('pengaturan.simpan');
});
