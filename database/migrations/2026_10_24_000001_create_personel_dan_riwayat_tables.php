<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Database personel selaras dengan personel_db.py di OTOMASI_GAJI_DPP (bagian 4h
 * dokumen konteks juru bayar): acuan pencocokan DPP, status aktif/keluar,
 * bank + rekening, dan riwayat perubahan yang tidak bisa diubah atau dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personel', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 3);                          // TNI | PNS
            $table->string('nama', 100);
            $table->string('pangkat', 60)->default('');          // pangkat/korps (TNI) atau golongan (PNS)
            $table->string('nrp', 30)->unique();                 // NRP/NIP hanya angka; dipakai mencocokkan DPP
            $table->string('nrp_asli', 30)->default('');         // tulisan aslinya
            $table->string('jabatan', 100)->default('');
            $table->string('ket', 60)->default('');
            $table->string('seksi', 100)->default('');
            $table->date('tgl_lahir')->nullable();
            $table->string('bank', 3)->default('');              // BRI | BSI | BTN | kosong
            $table->string('rekening', 30)->default('');
            $table->string('nama_rekening', 100)->default('');
            $table->string('status', 10)->default('aktif');      // aktif | keluar
            $table->date('tgl_keluar')->nullable();
            $table->string('alasan_keluar', 200)->default('');
            $table->string('tandai', 200)->default('');          // diisi impor ulang: perlu dicek juru bayar
            $table->timestamps();

            $table->index(['jenis', 'nama']);
            $table->index('status');
        });

        Schema::create('riwayat', function (Blueprint $table) {
            $table->id();
            $table->timestamp('waktu')->useCurrent();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('operator', 100);                     // nama pengguna saat kejadian
            $table->string('aksi', 30);                          // tambah | ubah | keluar | aktif kembali | hapus | ...
            $table->unsignedBigInteger('personel_id')->nullable()->index();   // bukan FK: personel yang dihapus tetap punya riwayat
            $table->string('nrp', 30)->default('');
            $table->string('nama', 100)->default('');
            $table->text('perubahan');                           // JSON {kolom: [lama, baru]}
        });

        $this->pasangPenjaga();
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat');
        Schema::dropIfExists('personel');
    }

    /** Riwayat hanya boleh ditambah: UPDATE dan DELETE ditolak oleh database sendiri. */
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
