<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Potongan satuan di luar DPP; selaras dengan tabel potongan/pinjaman/pokok/aturan di personel_db.py (bagian 4i dokumen konteks). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('potongan', function (Blueprint $table) {
            $table->foreignId('personel_id')->constrained('personel')->cascadeOnDelete();
            $table->string('kode', 30);                          // kode jenis (potongan.JENIS), bukan huruf kolom buku
            $table->unsignedBigInteger('nominal');               // 0 = tidak dipotong, jadi barisnya dihapus
            $table->primary(['personel_id', 'kode']);
        });

        Schema::create('pinjaman', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personel_id')->constrained('personel')->cascadeOnDelete();
            $table->string('pemberi', 10);                       // KOPERASI | LURJA
            $table->unsignedBigInteger('cicilan');
            $table->string('mulai', 7);                          // YYYY-MM, bulan potongan pertama
            $table->unsignedSmallInteger('tenor')->nullable();   // kosong = sisa tenor belum diketahui
            $table->string('tgl_cair', 10)->default('');
            $table->string('catatan', 500)->default('');
            $table->string('status', 5)->default('aktif');       // aktif | lunas | batal
            $table->timestamps();

            $table->index('personel_id');
        });

        Schema::create('pokok', function (Blueprint $table) {
            $table->foreignId('personel_id')->primary()->constrained('personel')->cascadeOnDelete();
            $table->string('bulan', 7);                          // simpanan pokok dipotong sekali di bulan ini
            $table->unsignedBigInteger('nominal');
        });

        Schema::create('aturan_potongan', function (Blueprint $table) {
            $table->string('kunci', 20)->primary();              // sisa_ybs | pokok | batas | arisan
            $table->text('nilai');                               // JSON
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aturan_potongan');
        Schema::dropIfExists('pokok');
        Schema::dropIfExists('pinjaman');
        Schema::dropIfExists('potongan');
    }
};
