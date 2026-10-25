<?php

namespace Tests\Feature;

use App\Models\ImporPersonel;
use App\Models\Personel;
use App\Models\Pinjaman;
use App\Models\ProsesGaji;
use App\Models\Riwayat;
use App\Models\User;
use App\Support\DataGaji;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Potongan satuan: penyimpanan dengan riwayat, hitungan Daftar Potongan lewat Python, dan impor dari pembukuan/arisan. */
class PotonganTest extends TestCase
{
    use RefreshDatabase;

    private const FIX = __DIR__.'/../fixtures/';

    protected function setUp(): void
    {
        parent::setUp();
        DataGaji::lupakan();
        Storage::fake('local');
        config(['sipaga.python' => base_path('python/.venv/bin/python')]);
    }

    private function masuk(string $peran = 'juru_bayar'): static
    {
        return $this->actingAs(User::factory()->create(['role' => $peran, 'name' => 'Pak '.$peran]));
    }

    private function orang(string $nama, string $nrp, string $bank = 'BRI'): Personel
    {
        return Personel::create(['jenis' => 'TNI', 'nama' => $nama, 'pangkat' => 'KAPTEN', 'nrp' => $nrp, 'nrp_asli' => $nrp, 'bank' => $bank]);
    }

    private function python(): void
    {
        if (! is_file(config('sipaga.python'))) $this->markTestSkipped('Python jembatan belum dipasang (python/.venv).');
    }

    private function xlsx(string $path, ?string $nama = null): UploadedFile
    {
        return new UploadedFile(self::FIX.$path, $nama ?? basename($path), 'application/octet-stream', null, true);
    }

    public function test_simpan_potongan_tetap_dengan_riwayat(): void
    {
        $budi = $this->orang('Budi', '21960123456789');
        $this->masuk('admin')->post("/potongan/orang/{$budi->id}", ['potongan' => ['TWP' => '150.000']])->assertRedirect(route('akun.index'));      // Admin tidak mengurus potongan
        $this->assertSame(0, DB::table('potongan')->count());

        $this->masuk()->post("/potongan/orang/{$budi->id}", ['potongan' => ['TWP' => 'Rp 150.000', 'SIMP_WAJIB' => '50000', 'PMI' => '']])->assertRedirect();
        $this->assertEquals(['TWP' => 150000, 'SIMP_WAJIB' => 50000], DB::table('potongan')->where('personel_id', $budi->id)->pluck('nominal', 'kode')->map(fn ($x) => (int) $x)->all());
        $h = Riwayat::where('aksi', 'potongan: ubah')->firstOrFail();
        $this->assertSame(['', '150.000'], $h->perubahan['TWP']);
        $this->assertSame('Pak juru_bayar', $h->operator);

        $n = Riwayat::count();
        $this->post("/potongan/orang/{$budi->id}", ['potongan' => ['TWP' => '150000']]);          // sama saja: tidak mencatat
        $this->assertSame($n, Riwayat::count());

        $this->post("/potongan/orang/{$budi->id}", ['potongan' => ['TWP' => '0']]);               // 0 = tidak dipotong
        $this->assertFalse(DB::table('potongan')->where('personel_id', $budi->id)->where('kode', 'TWP')->exists());

        $this->post("/potongan/orang/{$budi->id}", ['potongan' => ['TWP' => 'abc', 'PMI' => '-5']])->assertSessionHasErrors(['TWP' => 'Harus berupa angka.', 'PMI' => 'Tidak boleh negatif.']);
        $this->post("/potongan/orang/{$budi->id}", ['potongan' => ['NGAWUR' => '1']])->assertSessionHasErrors('_');
    }

    public function test_pinjaman_dicatat_diubah_dan_divalidasi(): void
    {
        $budi = $this->orang('Budi', '21960123456789');
        $this->masuk();

        $this->post('/potongan/pinjaman', ['personel_id' => $budi->id, 'pemberi' => 'KOPERASI', 'cicilan' => '800.000', 'mulai' => '2026-10', 'tenor' => '12'])->assertRedirect();
        $pj = Pinjaman::firstOrFail();
        $this->assertSame([800000, 12, 'aktif'], [(int) $pj->cicilan, $pj->tenor, $pj->status]);
        $this->assertSame('pinjaman: tambah', Riwayat::latest('id')->first()->aksi);

        $this->put("/potongan/pinjaman/{$pj->id}", ['pemberi' => 'KOPERASI', 'cicilan' => '800000', 'mulai' => '2026-10', 'tenor' => '12', 'status' => 'lunas']);
        $this->assertSame('lunas', $pj->refresh()->status);
        $this->assertSame('pinjaman: lunas', Riwayat::latest('id')->first()->aksi);

        $this->post('/potongan/pinjaman', ['personel_id' => $budi->id, 'pemberi' => 'BANK', 'cicilan' => '0', 'mulai' => '2026-13', 'tenor' => '0', 'tgl_cair' => 'kemarin'])
            ->assertSessionHasErrors(['pemberi', 'cicilan', 'mulai', 'tenor', 'tgl_cair']);
        $this->post('/potongan/pinjaman', ['personel_id' => 9999, 'pemberi' => 'LURJA', 'cicilan' => '1000', 'mulai' => '2026-10'])->assertSessionHasErrors('_');
        $this->assertSame(1, Pinjaman::count());
    }

    public function test_simpanan_pokok_dan_aturan(): void
    {
        $budi = $this->orang('Budi', '21960123456789');
        $this->masuk();

        $this->post("/potongan/orang/{$budi->id}/pokok", ['bulan' => '2026-10', 'nominal' => ''])->assertSessionHasErrors('nominal');   // belum ada nominal bawaan
        $aturan = ['sisa_ybs' => '5000', 'pokok' => '100.000', 'batas' => ['KOPERASI' => ['persen' => '40', 'sisa_min' => '', 'tenor_maks' => '24']]];
        $this->post('/potongan/aturan', $aturan)->assertRedirect();
        $this->assertSame(100000, \App\Support\PotonganData::aturan()['pokok']);
        $this->assertSame(40.0, (float) \App\Support\PotonganData::aturan()['batas']['KOPERASI']['persen']);

        $this->post("/potongan/orang/{$budi->id}/pokok", ['bulan' => '2026-10', 'nominal' => ''])->assertRedirect();   // memakai nominal bawaan
        $this->assertSame(100000, (int) DB::table('pokok')->where('personel_id', $budi->id)->value('nominal'));
        $this->post("/potongan/orang/{$budi->id}/pokok", ['bulan' => '', 'nominal' => '']);                         // dibatalkan
        $this->assertFalse(DB::table('pokok')->where('personel_id', $budi->id)->exists());

        $this->post('/potongan/aturan', ['sisa_ybs' => '', 'batas' => ['BJB' => ['persen' => '150']]])->assertSessionHasErrors(['sisa_ybs', 'batas.BJB.persen']);
        $this->masuk('admin')->post('/potongan/aturan', $aturan)->assertRedirect(route('akun.index'));
    }

    public function test_halaman_potongan_tampil(): void
    {
        $budi = $this->orang('Budi', '21960123456789');
        Pinjaman::create(['personel_id' => $budi->id, 'pemberi' => 'LURJA', 'cicilan' => 500000, 'mulai' => '2026-09', 'tenor' => null, 'status' => 'aktif']);
        $this->masuk();

        $this->get('/potongan')->assertOk()->assertSee('Budi')->assertSee('Daftar Potongan dibuat setiap proses gaji');
        $this->get('/potongan?lihat=pinjaman')->assertOk()->assertSee('belum punya tenor')->assertSee('LURJA');
        $this->get('/potongan?lihat=aturan')->assertOk()->assertSee('Batas pinjam per pemberi');
        $this->get("/potongan/orang/{$budi->id}")->assertOk()->assertSee('Potongan tetap per bulan')->assertSee('LURJA');
        $this->get('/potongan/orang/9999')->assertNotFound();
    }

    public function test_hapus_personel_ikut_menghapus_potongan_dan_mencatat_ringkasannya(): void
    {
        $budi = $this->orang('Budi', '21960123456789');
        DB::table('potongan')->insert(['personel_id' => $budi->id, 'kode' => 'TWP', 'nominal' => 150000]);
        Pinjaman::create(['personel_id' => $budi->id, 'pemberi' => 'KOPERASI', 'cicilan' => 800000, 'mulai' => '2026-10', 'status' => 'aktif']);

        $this->masuk()->delete("/personel/{$budi->id}")->assertRedirect();

        $this->assertSame(0, DB::table('potongan')->count() + Pinjaman::count());
        $h = Riwayat::where('aksi', 'hapus')->firstOrFail();
        $this->assertSame('TWP 150000', $h->perubahan['potongan'][0]);
        $this->assertStringContainsString('KOPERASI 800000/bln', $h->perubahan['pinjaman'][0]);
    }

    public function test_proses_gaji_menghasilkan_daftar_potongan(): void
    {
        $this->python();
        $budi = $this->orang('BUDI SANTOSO', '21960123456789');
        $dewi = $this->orang('DEWI LESTARI', '21980323456787');
        DB::table('potongan')->insert([['personel_id' => $budi->id, 'kode' => 'TWP', 'nominal' => 150000], ['personel_id' => $budi->id, 'kode' => 'SIMP_WAJIB', 'nominal' => 50000],
            ['personel_id' => $dewi->id, 'kode' => 'BRI_AYANI', 'nominal' => 2500000]]);
        Pinjaman::create(['personel_id' => $budi->id, 'pemberi' => 'KOPERASI', 'cicilan' => 800000, 'mulai' => '2026-08', 'tenor' => 12, 'status' => 'aktif']);
        Pinjaman::create(['personel_id' => $dewi->id, 'pemberi' => 'KOPERASI', 'cicilan' => 5000000, 'mulai' => '2026-06', 'tenor' => null, 'status' => 'aktif']);

        $pdf = fn ($n) => new UploadedFile(self::FIX.'dpp/'.$n, $n, 'application/pdf', null, true);
        $this->masuk()->post('/gaji', ['pdf' => [$pdf('DPP_GOL_II_NOVEMBER_2026.pdf'), $pdf('DPP_GOL_III_NOVEMBER_2026.pdf'), $pdf('DPP_SUSULAN_NOVEMBER_2026.pdf')],
            'buku' => $this->xlsx('dpp/PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx')]);
        $p = ProsesGaji::firstOrFail();
        $this->assertSame('selesai', $p->status, (string) $p->galat);

        $this->assertArrayHasKey('DAFTAR_POTONGAN_BRI_BLN_NOVEMBER_2026.xlsx', $p->berkasHasil());
        $s = $p->hasil()['satuan'];
        $this->assertSame(2, $s['ringkas']['dihitung']);
        $this->assertSame(1285950, $s['ringkas']['tunggakan']);                 // gaji Dewi 6.219.050 tidak cukup untuk 7,5 jt: koperasi yang menunggak
        $baris = collect($p->hasil()['pot_baris'])->keyBy('nama');
        $this->assertSame(1000000, $baris['BUDI SANTOSO']['total']);              // TWP 150rb + simpanan wajib 50rb + cicilan koperasi 800rb
        $this->assertSame(['cicilan ke-4 dari 12'], [$baris['BUDI SANTOSO']['pinjaman'][0]['ket']]);
        $this->assertSame(5000, $baris['DEWI LESTARI']['ybs']);

        DataGaji::lupakan();
        $this->get('/potongan')->assertOk()->assertSee('Daftar Potongan terakhir')->assertSee('Tunggakan koperasi');
        $this->get("/gaji/{$p->id}/langkah/5")->assertOk()->assertSee('Potongan satuan (BRI)')->assertSee('DAFTAR_POTONGAN_BRI_BLN_NOVEMBER_2026.xlsx');
    }

    public function test_impor_potongan_dari_pembukuan_lalu(): void
    {
        $this->python();
        $budi = $this->orang('BUDI SANTOSO', '21960123456789');
        $this->orang('DEWI LESTARI', '21980323456787');
        DB::table('potongan')->insert(['personel_id' => $budi->id, 'kode' => 'TWP', 'nominal' => 100000]);

        $this->masuk()->post('/personel/impor/baru/potongan', ['berkas' => [$this->xlsx('impor/PEMBUKUAN_POTONGAN_BRI_CONTOH.xlsx', 'PEMBUKUAN GAJI BRI BLN OKTOBER 2026.xlsx')]])->assertRedirect();
        $i = ImporPersonel::firstOrFail();
        $this->assertSame('potongan', $i->jenis);
        $this->assertCount(2, $i->rencana['ubah']);                                  // Budi (TWP 100rb -> 150rb) dan Dewi (TWP baru)
        $this->assertCount(1, $i->rencana['pinjaman_baru']);                         // cicilan koperasi Budi, tenor menyusul
        $this->assertSame(100000, (int) DB::table('potongan')->where('personel_id', $budi->id)->value('nominal'));    // belum diterapkan
        $this->get("/personel/impor/{$i->id}")->assertOk()->assertSee('Pinjaman berjalan yang akan dicatat')->assertSee('Tenor diisi menyusul');

        $this->post("/personel/impor/{$i->id}/terapkan")->assertRedirect(route('potongan.index'));
        $this->assertSame(150000, (int) DB::table('potongan')->where('personel_id', $budi->id)->where('kode', 'TWP')->value('nominal'));
        $pj = Pinjaman::firstOrFail();
        $this->assertSame([800000, null, '2026-10', 'KOPERASI'], [(int) $pj->cicilan, $pj->tenor, $pj->mulai, $pj->pemberi]);
        $this->assertSame(2, Riwayat::where('aksi', 'impor: potongan')->count());
        $this->assertSame(1, Riwayat::where('aksi', 'impor: pinjaman')->count());
    }

    public function test_impor_arisan_mengisi_dan_menghentikan(): void
    {
        $this->python();
        $budi = $this->orang('BUDI SANTOSO', '21960123456789');
        $dewi = $this->orang('DEWI LESTARI', '21980323456787');
        $joko = $this->orang('JOKO PRASETYO', '31990423456786');
        $lain = $this->orang('ORANG BANK LAIN', '44444444', 'BSI');
        DB::table('potongan')->insert([['personel_id' => $budi->id, 'kode' => 'ARISAN', 'nominal' => 115000], ['personel_id' => $joko->id, 'kode' => 'ARISAN', 'nominal' => 115000],
            ['personel_id' => $lain->id, 'kode' => 'ARISAN', 'nominal' => 115000]]);

        $this->masuk()->post('/personel/impor/baru/arisan', ['berkas' => [$this->xlsx('impor/DAFTAR_ARISAN_PERSIT_BRI_CONTOH.xlsx')]])->assertRedirect();
        $i = ImporPersonel::firstOrFail();
        $this->assertSame([$dewi->id], array_column($i->rencana['ubah'], 'id'));            // Dewi baru ikut, 120rb
        $this->assertSame([$joko->id], array_column($i->rencana['berhenti'], 'id'));        // Joko (BRI) tidak tercantum lagi

        $this->post("/personel/impor/{$i->id}/terapkan");
        $arisan = fn ($p) => (int) DB::table('potongan')->where('personel_id', $p->id)->where('kode', 'ARISAN')->value('nominal');
        $this->assertSame([115000, 120000, 0, 115000], [$arisan($budi), $arisan($dewi), $arisan($joko), $arisan($lain)]);   // bank lain tidak ikut berhenti
        $this->assertSame('DAFTAR_ARISAN_PERSIT_BRI_CONTOH.xlsx', \App\Support\PotonganData::aturan()['arisan']['BRI']['file']);
    }
}
