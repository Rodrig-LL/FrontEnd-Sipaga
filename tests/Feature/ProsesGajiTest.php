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

    public function test_admin_tidak_bisa_menjalankan_proses(): void
    {
        $this->personel();

        $this->sebagai('admin')->post('/gaji', $this->berkas())->assertRedirect(route('akun.index'));
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
        $this->get("/gaji/{$p->id}/langkah/5")->assertOk()->assertSee('Tahapan proses')->assertSee('href="'.route('gaji.index').'"', false)->assertSee('COCOK')->assertSee('42.167.540')->assertSee('PEMBUKUAN_GAJI_BRI_BLN_NOVEMBER_2026.xlsx');
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

    public function test_sisa_hasil_proses_lama_dengan_id_sama_tidak_menggagalkan_proses_baru(): void
    {
        if (! is_file(config('sipaga.python'))) $this->markTestSkipped('Python jembatan belum dipasang (python/.venv).');
        $this->personel();
        $fix = fn ($n) => file_get_contents(self::FIX.$n);
        $berkas = ['DPP_GOL_II_NOVEMBER_2026.pdf', 'DPP_GOL_III_NOVEMBER_2026.pdf', 'DPP_SUSULAN_NOVEMBER_2026.pdf'];
        $p = ProsesGaji::create(['status' => 'menunggu', 'berkas' => ['pdf' => $berkas, 'buku' => 'PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx']]);
        foreach (array_merge($berkas, ['PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx']) as $n) Storage::disk('local')->put($p->folder('masuk/'.$n), $fix($n));
        // Sisa proses lama: berkas hasil dengan nama yang sama persis, dan hasil.json basi
        Storage::disk('local')->put($p->folder('hasil/PEMBUKUAN_GAJI_BRI_BLN_NOVEMBER_2026.xlsx'), 'basi');
        Storage::disk('local')->put($p->folder('hasil.json'), json_encode(['ok' => true, 'bulan' => 'BASI']));

        app(\App\Services\JembatanPython::class)->jalankan($p);

        $p->refresh();
        $this->assertSame('selesai', $p->status, (string) $p->galat);
        $this->assertSame('NOVEMBER 2026', $p->bulan);
        $this->assertNotSame('basi', Storage::disk('local')->get($p->folder('hasil/PEMBUKUAN_GAJI_BRI_BLN_NOVEMBER_2026.xlsx')));
    }

    private function prosesJadi(string $status = 'selesai', string $bulan = 'NOVEMBER 2026'): ProsesGaji
    {
        $p = ProsesGaji::create(['status' => $status, 'bulan' => $bulan, 'selesai_at' => now(), 'berkas' => ['pdf' => ['a.pdf'], 'buku' => 'b.xlsx']]);
        Storage::disk('local')->put($p->folder('masuk/a.pdf'), 'pdf');
        Storage::disk('local')->put($p->folder('hasil/PEMBUKUAN.xlsx'), 'xlsx');
        Storage::disk('local')->put($p->folder('hasil.json'), '{}');

        return $p;
    }

    public function test_langkah_1_memakai_stepper_dan_tata_letak_yang_sama_dengan_langkah_lain(): void
    {
        $this->sebagai('juru_bayar');

        $this->get('/gaji')->assertOk()->assertSee('Langkah 1 dari 5: Unggah berkas')->assertSee('Tahapan proses')
            ->assertSee('Unggah berkas')->assertSee('Periksa hasil baca')->assertSee('Unduh hasil')
            ->assertSee('Belum ada hasil proses untuk dibuka')->assertDontSee(route('gaji.langkah', [1, 2]));

        $p = $this->prosesJadi();
        \App\Support\DataGaji::lupakan();       // cache statis hanya hidup selama satu permintaan di produksi
        $this->get('/gaji')->assertOk()->assertSee('Lanjut: Periksa hasil baca')->assertSee(route('gaji.langkah', [$p, 2]))
            ->assertSee(route('gaji.langkah', [$p, 5]))->assertSee('dipakai Dashboard, Slip, dan Rekap');
    }

    public function test_hapus_proses_menghapus_baris_berkas_dan_mencatat_riwayat(): void
    {
        $p = $this->prosesJadi();
        $gagal = $this->prosesJadi('gagal', '');

        $this->sebagai('admin')->get('/gaji')->assertRedirect(route('akun.index'));
        $this->delete("/gaji/{$p->id}")->assertRedirect(route('akun.index'));
        $this->assertTrue(ProsesGaji::whereKey($p->id)->exists());

        $this->sebagai('juru_bayar')->get('/gaji')->assertOk()->assertSee('Ya, hapus')->assertSee('Unduh dulu');
        $this->delete("/gaji/{$p->id}")->assertRedirect(route('gaji.index'))->assertSessionHas('ok');

        $this->assertFalse(ProsesGaji::whereKey($p->id)->exists());
        $this->assertFalse(Storage::disk('local')->exists($p->folder()), 'folder berkas proses ikut terhapus');
        $this->assertTrue(Storage::disk('local')->exists($gagal->folder('hasil.json')), 'proses lain tidak tersentuh');
        $h = \App\Models\Riwayat::where('aksi', 'proses: hapus')->firstOrFail();
        $this->assertNull($h->personel_id);
        $this->assertSame("#{$p->id} NOVEMBER 2026 · selesai", $h->perubahan['Proses gaji'][0]);
        $this->assertSame('a.pdf, b.xlsx', $h->perubahan['Berkas'][0]);

        $this->delete("/gaji/{$gagal->id}")->assertRedirect();                 // yang gagal juga bisa dihapus
        $this->assertSame(0, ProsesGaji::count());
        $this->delete('/gaji/999999')->assertNotFound();
    }

    public function test_proses_yang_masih_berjalan_tidak_bisa_dihapus_kecuali_macet(): void
    {
        $p = $this->prosesJadi('berjalan');
        $this->sebagai('juru_bayar');

        $this->delete("/gaji/{$p->id}")->assertRedirect(route('gaji.index'))->assertSessionHasErrors('hapus');
        $this->assertTrue(ProsesGaji::whereKey($p->id)->exists());
        $this->assertTrue(Storage::disk('local')->exists($p->folder('hasil.json')));
        $this->get('/gaji')->assertOk()->assertSee('masih berjalan');

        // Macet: tidak ada kemajuan melewati batas waktu proses + 2 menit
        ProsesGaji::whereKey($p->id)->update(['updated_at' => now()->subSeconds(config('sipaga.python_timeout') + 300)]);
        $this->delete("/gaji/{$p->id}")->assertRedirect()->assertSessionHasNoErrors();
        $this->assertFalse(ProsesGaji::whereKey($p->id)->exists());
    }
}
