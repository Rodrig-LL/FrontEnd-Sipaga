<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Akun boleh dihapus, tetapi FK riwayat.user_id (ON DELETE SET NULL) akan meng-UPDATE riwayat, dan itu
 * ditolak trigger "riwayat hanya bisa ditambah" (di SQLite aksi FK memicu trigger). Riwayat sudah menyimpan
 * nama pengguna sebagai teks (kolom operator), jadi user_id cukup menjadi penanda biasa, seperti personel_id.
 *
 * SQLite membangun ulang tabel saat FK dilepas, dan trigger ikut hilang, jadi trigger dipasang ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->buangPenjaga();
        Schema::table('riwayat', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        $this->pasangPenjaga();
    }

    public function down(): void
    {
        // Tidak dikembalikan: FK itu justru yang menghalangi penghapusan akun.
    }

    private function buangPenjaga(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS riwayat_tidak_diubah');
        DB::unprepared('DROP TRIGGER IF EXISTS riwayat_tidak_dihapus');
    }

    /** Sama dengan migrasi 2026_10_24_000001: UPDATE dan DELETE ditolak oleh database sendiri. */
    private function pasangPenjaga(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::unprepared("CREATE TRIGGER riwayat_tidak_diubah BEFORE UPDATE ON riwayat BEGIN SELECT RAISE(ABORT, 'riwayat tidak boleh diubah'); END");
            DB::unprepared("CREATE TRIGGER riwayat_tidak_dihapus BEFORE DELETE ON riwayat BEGIN SELECT RAISE(ABORT, 'riwayat tidak boleh dihapus'); END");
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            DB::unprepared("CREATE TRIGGER riwayat_tidak_diubah BEFORE UPDATE ON riwayat FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'riwayat tidak boleh diubah'");
            DB::unprepared("CREATE TRIGGER riwayat_tidak_dihapus BEFORE DELETE ON riwayat FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'riwayat tidak boleh dihapus'");
        }
    }
};
