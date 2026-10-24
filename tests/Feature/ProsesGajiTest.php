<?php

namespace Tests\Feature;

use App\Models\Personel;
use App\Models\ProsesGaji;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProsesGajiTest extends TestCase
{
    use RefreshDatabase;

    private const FIX = __DIR__.'/../fixtures/dpp/';

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['sipaga.python' => base_path('python/.venv/bin/python')]);
    }

    private function sebagai(string $peran): static
    {
        return $this->actingAs(User::factory()->create(['role' => $peran, 'name' => 'Pak '.$peran]));
    }

    private function personel(): void
    {
        Personel::create(['jenis' => 'TNI', 'nama' => 'Ahmad Fauzi', 'nrp' => '11112214', 'nrp_asli' => '11112214', 'bank' => 'BRI']);
    }

    private function berkas(): array
    {
        $pdf = fn ($n) => new UploadedFile(self::FIX.$n, $n, 'application/pdf', null, true);

        return ['pdf' => [$pdf('DPP_GOL_II_NOVEMBER_2026.pdf'), $pdf('DPP_GOL_III_NOVEMBER_2026.pdf'), $pdf('DPP_SUSULAN_NOVEMBER_2026.pdf')],
            'buku' => new UploadedFile(self::FIX.'PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx', 'PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true)];
    }

    public function test_operator_tidak_boleh_menjalankan_proses(): void
    {
        $this->personel();

        $this->sebagai('operator')->post('/gaji', $this->berkas())->assertForbidden();
        $this->assertSame(0, ProsesGaji::count());
    }

    public function test_ditolak_bila_data_personel_kosong(): void
    {
        $this->sebagai('juru_bayar')->post('/gaji', $this->berkas())
            ->assertSessionHasErrors(['pdf' => 'Data personel masih kosong. Isi dulu lewat Data Personel, lalu proses lagi.']);
        $this->assertSame(0, ProsesGaji::count());
    }

    public function test_validasi_berkas(): void
    {
        $this->personel();
        $this->sebagai('juru_bayar');

        $this->post('/gaji', [])->assertSessionHasErrors(['pdf', 'buku']);
        $this->post('/gaji', ['pdf' => [UploadedFile::fake()->create('a.txt', 1, 'text/plain')], 'buku' => UploadedFile::fake()->create('b.xls', 1, 'application/vnd.ms-excel')])
            ->assertSessionHasErrors(['pdf.0', 'buku']);
        $this->post('/gaji', $this->berkas() + ['bulan' => 'november'])->assertSessionHasErrors('bulan');
    }

    public function test_jalur_penuh_dengan_python_sungguhan(): void
    {
        if (! is_file(config('sipaga.python'))) $this->markTestSkipped('Python jembatan belum dipasang (python/.venv).');
        $this->personel();

        $this->sebagai('juru_bayar')->post('/gaji', $this->berkas())->assertRedirect();
        $p = ProsesGaji::firstOrFail();

        $this->assertSame('selesai', $p->status, (string) $p->galat);
        $this->assertSame('NOVEMBER 2026', $p->bulan);
        $this->assertSame(14, $p->ringkasan['tingkat']['TINGGI']);        // angka acuan dokumen konteks juru bayar
        $this->assertSame(42167540, $p->ringkasan['total_dpp']);
        $this->assertTrue($p->ringkasan['rekonsiliasi_cocok']);
        $this->assertArrayHasKey('KURANG', $p->hasil()['pencocokan']['hitung']);   // pencocokan dengan Data Personel ikut jalan
        $this->assertArrayHasKey('PEMBUKUAN_GAJI_BRI_BLN_NOVEMBER_2026.xlsx', $p->berkasHasil());

        $this->get("/gaji/{$p->id}")->assertRedirect(route('gaji.langkah', [$p, 2]));
        $this->get("/gaji/{$p->id}/unduh/PEMBUKUAN_GAJI_BRI_BLN_NOVEMBER_2026.xlsx")->assertOk();
        $this->get("/gaji/{$p->id}/unduh/..%2F..%2F.env")->assertNotFound();
        $this->get("/gaji/{$p->id}/unduh/tugas.json")->assertNotFound();
    }

    public function test_langkah_2_sampai_5_menampilkan_hasil_nyata(): void
    {
        if (! is_file(config('sipaga.python'))) $this->markTestSkipped('Python jembatan belum dipasang (python/.venv).');
        $this->personel();
        $this->sebagai('juru_bayar')->post('/gaji', $this->berkas());
        $p = ProsesGaji::firstOrFail();

        // 2: hasil baca per PDF dan anggota; satu anggota sengaja salah hitung di data uji
        $this->get("/gaji/{$p->id}/langkah/2")->assertOk()->assertSee('DPP_GOL_II_NOVEMBER_2026.pdf')
            ->assertSee('HENDRA GUNAWAN')->assertSee('BERSIH: hitung 3,491,070 vs tertulis 4,491,070');
        // 3: pencocokan personel (default: yang perlu diperiksa) dan temuan per tingkat
        $this->get("/gaji/{$p->id}/langkah/3")->assertOk()->assertSee('Kurang (tidak ada di DPP)')->assertSee('Ahmad Fauzi');
        $this->get("/gaji/{$p->id}/langkah/3?lihat=temuan&tingkat=TINGGI")->assertOk()->assertSee('NRP ganda di DPP');
        $this->get("/gaji/{$p->id}/langkah/3?lihat=temuan&tingkat=INFO")->assertOk()->assertDontSee('NRP ganda di DPP');
        // 4: gaji yang berubah; tanpa tombol pilih angka lama/baru
        $this->get("/gaji/{$p->id}/langkah/4")->assertOk()->assertSee('BUDI SANTOSO')->assertSee('9.139.530')->assertDontSee('Pakai lama');
        // 5: rekonsiliasi dan unduhan
        $this->get("/gaji/{$p->id}/langkah/5")->assertOk()->assertSee('COCOK')->assertSee('42.167.540')->assertSee('PEMBUKUAN_GAJI_BRI_BLN_NOVEMBER_2026.xlsx');
        $this->get("/gaji/{$p->id}/langkah/1")->assertNotFound();
        $this->get("/gaji/{$p->id}/langkah/6")->assertNotFound();
    }

    public function test_langkah_untuk_proses_yang_belum_selesai_kembali_ke_status(): void
    {
        $p = ProsesGaji::create(['status' => 'berjalan', 'berkas' => ['pdf' => ['a.pdf'], 'buku' => 'b.xlsx']]);

        $this->sebagai('juru_bayar')->get("/gaji/{$p->id}/langkah/2")->assertRedirect(route('gaji.show', $p));
        $this->get("/gaji/{$p->id}")->assertOk()->assertSee('Sedang membaca');
    }

    public function test_waktu_aplikasi_wib_dan_bahasa_indonesia(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('id', app()->getLocale());
        $this->assertSame('+07:00', now()->format('P'));
        $this->assertSame('Oktober', now()->setDate(2026, 10, 24)->translatedFormat('F'));
    }

    public function test_pesan_galat_python_sampai_ke_pengguna(): void
    {
        if (! is_file(config('sipaga.python'))) $this->markTestSkipped('Python jembatan belum dipasang (python/.venv).');
        $this->personel();
        $b = $this->berkas();
        $b['pdf'] = [UploadedFile::fake()->createWithContent('bukan.pdf', "%PDF-1.4\nrusak")];

        $this->sebagai('juru_bayar')->post('/gaji', $b)->assertRedirect();
        $p = ProsesGaji::firstOrFail();

        $this->assertSame('gagal', $p->status);
        $this->assertNotEmpty($p->galat);
        $this->get("/gaji/{$p->id}")->assertOk()->assertSee('Proses gagal');
    }

    public function test_python_tidak_terpasang_memberi_pesan_jelas(): void
    {
        $this->personel();
        config(['sipaga.python' => '/tidak/ada/python']);

        $this->sebagai('juru_bayar')->post('/gaji', $this->berkas())->assertRedirect();
        $p = ProsesGaji::firstOrFail();

        $this->assertSame('gagal', $p->status);
        $this->assertStringContainsString('Python', $p->galat);
    }
}
