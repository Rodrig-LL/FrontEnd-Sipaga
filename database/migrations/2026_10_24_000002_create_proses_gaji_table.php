<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proses_gaji', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 10)->default('menunggu');   // menunggu | berjalan | selesai | gagal
            $table->string('bulan', 30)->default('');            // mis. NOVEMBER 2026, diisi dari PDF
            $table->string('bulan_paksa', 30)->default('');      // bila operator menetapkan sendiri
            $table->json('berkas');                              // {pdf: [nama...], buku: nama}
            $table->json('ringkasan')->nullable();               // angka untuk daftar proses; rinciannya di hasil.json
            $table->text('galat')->nullable();
            $table->timestamp('mulai_at')->nullable();
            $table->timestamp('selesai_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proses_gaji');
    }
};
