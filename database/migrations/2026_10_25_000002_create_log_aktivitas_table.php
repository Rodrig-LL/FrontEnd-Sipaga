<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Log aktivitas aplikasi (siapa masuk, siapa mengubah apa), dibaca Admin.
 * Seperti riwayat personel, hanya boleh ditambah: UPDATE dan DELETE ditolak oleh database sendiri.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('log_aktivitas', function (Blueprint $table) {
            $table->id();
            $table->timestamp('waktu')->useCurrent();
            $table->unsignedBigInteger('user_id')->nullable()->index();   // bukan FK: log tetap utuh walau akunnya kelak dibuang
            $table->string('pengguna', 100)->default('');                 // nama saat kejadian
            $table->string('username', 60)->default('');
            $table->string('aksi', 30)->index();                          // kode, lihat LogAktivitas::AKSI
            $table->string('keterangan', 500)->default('');
            $table->string('ip', 45)->default('');
        });

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER log_aktivitas_tidak_diubah BEFORE UPDATE ON log_aktivitas BEGIN SELECT RAISE(ABORT, 'log tidak boleh diubah'); END");
            DB::unprepared("CREATE TRIGGER log_aktivitas_tidak_dihapus BEFORE DELETE ON log_aktivitas BEGIN SELECT RAISE(ABORT, 'log tidak boleh dihapus'); END");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER log_aktivitas_tidak_diubah BEFORE UPDATE ON log_aktivitas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'log tidak boleh diubah'");
            DB::unprepared("CREATE TRIGGER log_aktivitas_tidak_dihapus BEFORE DELETE ON log_aktivitas FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'log tidak boleh dihapus'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('log_aktivitas');
    }
};
