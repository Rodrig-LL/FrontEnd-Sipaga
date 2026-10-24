<?php

namespace Tests\Feature;

use App\Models\Pengaturan;
use App\Models\Personel;
use App\Models\ProsesGaji;
use App\Models\User;
use App\Support\DataGaji;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Dasbor, Slip, dan Rekap membaca hasil proses gaji nyata (Python dengan data sintetis di tests/fixtures/dpp). */
class HasilGajiTest extends TestCase
{
    use RefreshDatabase;

    private const FIX = __DIR__.'/../fixtures/dpp/';

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

    private function prosesNyata(): ProsesGaji
    {
        if (! is_file(config('sipaga.python'))) $this->markTestSkipped('Python jembatan belum dipasang (python/.venv).');
        Personel::create(['jenis' => 'TNI', 'nama' => 'Ahmad Fauzi', 'nrp' => '11112214', 'nrp_asli' => '11112214', 'bank' => 'BRI']);
        $pdf = fn ($n) => new UploadedFile(self::FIX.$n, $n, 'application/pdf', null, true);
        $this->masuk()->post('/gaji', [
            'pdf' => [$pdf('DPP_GOL_II_NOVEMBER_2026.pdf'), $pdf('DPP_GOL_III_NOVEMBER_2026.pdf'), $pdf('DPP_SUSULAN_NOVEMBER_2026.pdf')],
            'buku' => new UploadedFile(self::FIX.'PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx', 'PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx', 'application/octet-stream', null, true),
        ]);
        DataGaji::lupakan();

        return ProsesGaji::where('status', 'selesai')->firstOrFail();
    }

    public function test_tanpa_proses_halaman_menawarkan_mulai_proses(): void
    {
        $this->masuk();

        foreach (['/dasbor', '/slip', '/rekap', '/slip/cetak?semua=1'] as $url) {
            $this->get($url)->assertOk()->assertSee('Belum ada gaji yang diproses');
        }
        $this->get('/rekap/bulanan')->assertRedirect(route('rekap.index'));
        $this->get('/slip/1')->assertNotFound();
    }

    public function test_dasbor_dari_data_dpp(): void
    {
        $this->prosesNyata();

        $this->get('/dasbor')->assertOk()->assertSee('November 2026')->assertSee('Anggota menurut DPP')
            ->assertSee('Perlu perhatian')->assertSee('Hitungan DPP tidak konsisten')->assertSee('RIZKY FEBRIAN')->assertSee('71,0 jt')->assertDontSee('Data contoh');
    }

    public function test_daftar_slip_dan_saringan(): void
    {
        $this->prosesNyata();

        $this->get('/slip')->assertOk()->assertSee('BUDI SANTOSO')->assertSee('HENDRA GUNAWAN')->assertSee('Tanpa temuan');
        $this->get('/slip?q=hendra')->assertOk()->assertSee('HENDRA GUNAWAN')->assertDontSee('BUDI SANTOSO');
        $this->get('/slip?q=21960123456789')->assertOk()->assertSee('BUDI SANTOSO')->assertDontSee('HENDRA GUNAWAN');
        // Andi: hitungan DPP tidak konsisten; Agus: NRP ganda di DPP -> keduanya perlu dicek, Budi tidak
        $this->get('/slip?status=verif')->assertOk()->assertSee('ANDI PERMANA')->assertSee('AGUS WIJAYA')->assertDontSee('BUDI SANTOSO');
        $this->get('/slip?status=siap')->assertOk()->assertSee('BUDI SANTOSO')->assertDontSee('ANDI PERMANA');
    }

    public function test_slip_per_anggota_menampilkan_rincian_dpp_dan_catatan_wajib(): void
    {
        $this->prosesNyata();
        $hendra = collect(DataGaji::slip())->firstWhere('nama', 'ANDI PERMANA');   // satu-satunya yang hitungan DPP-nya tidak konsisten

        $this->get('/slip/'.$hendra['n'])->assertOk()->assertSee('Slip gaji ANDI PERMANA', false)
            ->assertSee('Jumlah penghasilan')->assertSee('JUMLAH DIBAYARKAN')
            ->assertSee('BERSIH: hitung 3,491,070 vs tertulis 4,491,070')
            ->assertSee('Belum termasuk potongan satuan')           // catatan yang wajib ada di setiap slip
            ->assertSee('Hitungan di DPP tidak konsisten');
        $this->get('/slip/9999')->assertNotFound();
        $this->get('/slip/cetak?n='.$hendra['n'])->assertOk()->assertSee('1 slip siap dicetak');
        $this->get('/slip/cetak?semua=1')->assertOk()->assertSee('10 slip siap dicetak')->assertSee('perlu dicek');
        $this->get('/slip/cetak?ids[]='.$hendra['n'].'&ids[]=99999')->assertOk()->assertSee('1 slip siap dicetak');
    }

    public function test_lima_laporan_dari_data_dpp(): void
    {
        $this->prosesNyata();

        $this->get('/rekap')->assertOk()->assertSee('Rekap gaji bulanan')->assertSee('Jumlah dibayarkan');
        foreach (array_keys(\App\Support\Laporan::DAFTAR) as $id) {
            $this->get("/rekap/$id")->assertOk();
            $this->get("/rekap/$id?cetak=1")->assertOk();
        }
        $this->get('/rekap/penerimaan')->assertOk()->assertSee('BUDI SANTOSO')->assertSee('Jumlah seluruh anggota')->assertSee('70.998.220');      // seluruh anggota DPP; 42.167.540 hanya yang cocok dengan pembukuan
        $this->get('/rekap/temuan')->assertOk()->assertSee('NRP ganda di DPP');
        $this->get('/rekap/tidak-ada')->assertNotFound();

        $csv = $this->get('/rekap/penerimaan/unduh')->assertOk();
        $isi = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);
        $this->assertStringContainsString('"=""21960123456789"""', $isi);      // NRP tidak boleh kehilangan angka di Excel
        $this->assertStringContainsString('70998220', $isi);                    // angka ditulis tanpa titik ribuan
        $this->assertStringContainsString('sipaga-penerimaan-november-2026.csv', $csv->headers->get('content-disposition'));
    }

    public function test_pilih_periode_dari_proses_selesai(): void
    {
        $p = $this->prosesNyata();
        $lain = ProsesGaji::create(['status' => 'selesai', 'bulan' => 'OKTOBER 2026', 'berkas' => ['pdf' => ['a.pdf'], 'buku' => 'b.xlsx'], 'selesai_at' => now()]);
        DataGaji::lupakan();

        $this->post('/periode', ['proses' => $lain->id]);
        $this->assertSame($lain->id, session('proses_id'));
        $this->post('/periode', ['proses' => 99999]);
        $this->assertSame($lain->id, session('proses_id'));      // id yang tidak ada diabaikan
        $this->post('/periode', ['proses' => $p->id]);
        $this->assertSame($p->id, session('proses_id'));
    }

    public function test_pengaturan_kop_tersimpan_di_database_dan_tampil_di_slip(): void
    {
        $this->prosesNyata();
        $semua = ['instansi' => 'DITAJENAD', 'alamat' => 'Jl. Contoh 1', 'kota' => 'Jakarta', 'juru' => 'Mayor Budi', 'juru_nrp' => '11112233'];

        $this->masuk('operator')->post('/pengaturan', $semua)->assertForbidden();
        $this->assertSame([], Pengaturan::semua());

        $this->masuk('juru_bayar')->post('/pengaturan', $semua + ['lega' => '1'])->assertRedirect();
        $this->post('/keluar');                                      // keluar menghapus session; kop harus tetap ada
        $this->assertSame('DITAJENAD', Pengaturan::ambil('instansi'));

        $n = array_key_first(DataGaji::slip());
        $this->masuk('operator')->get("/slip/$n")->assertOk()->assertSee('DITAJENAD')->assertSee('Mayor Budi')->assertSee('Jakarta');
    }
}
