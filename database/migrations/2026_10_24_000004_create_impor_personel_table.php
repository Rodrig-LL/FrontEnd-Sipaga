<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impor_personel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('jenis', 10);                         // nominatif | bank
            $table->string('status', 10)->default('pratinjau');  // pratinjau | diterapkan
            $table->json('berkas');                              // nama berkas asli (berkasnya sendiri dibuang setelah dibaca)
            $table->longText('rencana');                         // JSON rencana dari Python
            $table->string('sidik', 40);                         // kondisi Data Personel saat pratinjau dibuat
            $table->json('hasil')->nullable();                   // {tambah, ubah, tandai} setelah diterapkan
            $table->timestamp('diterapkan_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('impor_personel');
    }
};
