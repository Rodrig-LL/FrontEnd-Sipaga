<?php

namespace Tests\Feature;

use App\Models\ImporPersonel;
use App\Models\Personel;
use App\Models\Riwayat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\ButuhFixture;
use Tests\TestCase;

/** Impor nominatif dan bank/rekening lewat jembatan Python, memakai berkas sintetis di tests/fixtures. */
class ImporPersonelTest extends TestCase
{
    use ButuhFixture;
    use RefreshDatabase;


    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['sipaga.python' => base_path('python/.venv/bin/python')]);
        if (! is_file(config('sipaga.python'))) $this->markTestSkipped('Python jembatan belum dipasang (python/.venv).');
    }

    private function masuk(string $peran = 'juru_bayar'): static
    {
        return $this->actingAs(User::factory()->create(['role' => $peran, 'name' => 'Pak '.$peran]));
    }

    private function xlsx(string $path, ?string $nama = null): UploadedFile
    {
        return new UploadedFile($this->fixture($path), $nama ?? basename($path), 'application/octet-stream', null, true);
    }

    private function nominatif(): array
    {
        return [$this->xlsx('impor/NOMINATIF_MILITER_CONTOH.xlsx'), $this->xlsx('impor/NOMINATIF_PNS_CONTOH.xlsx')];
    }

    private function lama(): void
    {
        Personel::create(['jenis' => 'TNI', 'nama' => 'Ahmad Fauzi', 'pangkat' => 'MAYOR', 'nrp' => '11112214', 'nrp_asli' => '11112214', 'bank' => 'BRI', 'rekening' => '123']);
        Personel::create(['jenis' => 'TNI', 'nama' => 'Orang Lama', 'pangkat' => 'SERDA', 'nrp' => '99999999', 'nrp_asli' => '99999999']);
    }

    public function test_pratinjau_nominatif_tidak_mengubah_database(): void
    {
        $this->lama();
        $this->masuk()->post('/personel/impor/baru/nominatif', ['berkas' => $this->nominatif()])->assertRedirect();

        $i = ImporPersonel::firstOrFail();
        $this->assertSame('pratinjau', $i->status);
        $this->assertCount(5, $i->rencana['tambah']);       // 3 militer + 2 PNS; baris ber-NRP "12" dilewati
        $this->assertCount(1, $i->rencana['ubah']);
        $this->assertCount(1, $i->rencana['hilang']);
        $this->assertSame(2, Personel::count());             // belum berubah
        $this->assertSame(0, Riwayat::count());
        $this->assertSame([], Storage::disk('local')->allFiles('impor'));   // berkas unggahan dibuang

        $this->get("/personel/impor/{$i->id}")->assertOk()->assertSee('Periksa sebelum diterapkan')->assertSee('Baru Sekali')
            ->assertSee('Dewi Anggraini')->assertSee('Pangkat/gol.: MAYOR → MAYOR INF', false)->assertSee('Orang Lama');
    }

    public function test_terapkan_nominatif_menulis_data_dan_riwayat_per_baris(): void
    {
        $this->lama();
        $this->masuk()->post('/personel/impor/baru/nominatif', ['berkas' => $this->nominatif()]);
        $i = ImporPersonel::firstOrFail();

        $this->post("/personel/impor/{$i->id}/terapkan")->assertRedirect(route('personel.index'));

        $this->assertSame(7, Personel::count());
        $dewi = Personel::where('nrp', '198201012006042001')->firstOrFail();
        $this->assertSame('PNS', $dewi->jenis);
        $this->assertSame('1982-01-01', $dewi->tgl_lahir->format('Y-m-d'));
        $this->assertSame('aktif', $dewi->status);
        $ahmad = Personel::where('nrp', '11112214')->firstOrFail();
        $this->assertSame('MAYOR INF', $ahmad->pangkat);
        $this->assertSame('BRI', $ahmad->bank);               // bank dan rekening tidak disentuh nominatif
        $this->assertSame('123', $ahmad->rekening);
        $this->assertStringContainsString('Tidak tercantum di', Personel::where('nrp', '99999999')->value('tandai'));

        $this->assertSame(5, Riwayat::where('aksi', 'impor: tambah')->count());
        $this->assertSame(1, Riwayat::where('aksi', 'impor: ubah')->count());
        $this->assertSame(1, Riwayat::where('aksi', 'impor: ditandai')->count());
        $ringkas = Riwayat::where('aksi', 'impor')->firstOrFail();
        $this->assertNull($ringkas->personel_id);
        $this->assertSame(5, $ringkas->perubahan['tambah']);
        $this->assertSame('Pak juru_bayar', $ringkas->operator);
        $this->assertSame('diterapkan', $i->refresh()->status);

        // Tidak bisa diterapkan dua kali
        $this->post("/personel/impor/{$i->id}/terapkan")->assertRedirect()->assertSessionHasErrors('impor');
        $this->assertSame(7, Personel::count());
    }

    public function test_impor_ulang_nominatif_yang_sama_tidak_mengubah_apa_apa(): void
    {
        $this->masuk()->post('/personel/impor/baru/nominatif', ['berkas' => $this->nominatif()]);
        $this->post('/personel/impor/'.ImporPersonel::firstOrFail()->id.'/terapkan');

        $this->post('/personel/impor/baru/nominatif', ['berkas' => $this->nominatif()]);
        $kedua = ImporPersonel::latest('id')->firstOrFail();
        $this->assertSame([], $kedua->rencana['tambah']);
        $this->assertSame([], $kedua->rencana['ubah']);
        $this->assertSame([], $kedua->rencana['hilang']);
        $this->assertSame(6, $kedua->rencana['tetap']);
        $this->get("/personel/impor/{$kedua->id}")->assertOk()->assertSee('Tidak ada yang perlu diubah');
    }

    public function test_pratinjau_basi_ditolak_bila_data_personel_berubah(): void
    {
        $this->lama();
        $this->masuk()->post('/personel/impor/baru/nominatif', ['berkas' => $this->nominatif()]);
        $i = ImporPersonel::firstOrFail();

        // Pengguna lain mengubah data personel setelah pratinjau dibuat
        $p = Personel::where('nrp', '11112214')->firstOrFail();
        $this->put("/personel/{$p->id}", ['jenis' => 'TNI', 'nama' => 'Ahmad Fauzi', 'nrp_asli' => '11112214', 'status' => 'aktif', 'jabatan' => 'Diubah orang lain']);

        $this->get("/personel/impor/{$i->id}")->assertOk()->assertSee('Data personel berubah sejak pratinjau');
        $this->post("/personel/impor/{$i->id}/terapkan")->assertSessionHasErrors('impor');
        $this->assertSame(2, Personel::count());
        $this->assertSame('pratinjau', $i->refresh()->status);
    }

    public function test_impor_bank_dan_rekening_dari_pembukuan(): void
    {
        Personel::create(['jenis' => 'TNI', 'nama' => 'Budi Santoso', 'pangkat' => 'KAPTEN', 'nrp' => '21960123456789', 'nrp_asli' => '21960123456789']);
        Personel::create(['jenis' => 'TNI', 'nama' => 'Orang Tak Ada Di Buku', 'nrp' => '55555555', 'nrp_asli' => '55555555']);

        $this->masuk()->post('/personel/impor/baru/bank', ['berkas' => [$this->xlsx('dpp/PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx')]])->assertRedirect();
        $i = ImporPersonel::firstOrFail();
        $this->assertSame('bank', $i->jenis);
        $this->assertCount(1, $i->rencana['ubah']);
        $this->assertSame('BRI', $i->rencana['ubah'][0]['perubahan']['bank'][1]);
        $this->assertNotEmpty($i->rencana['tidak_ketemu']);                  // anggota buku lain tidak ada di database
        $this->assertSame('', Personel::where('nrp', '21960123456789')->value('bank'));

        $this->post("/personel/impor/{$i->id}/terapkan");
        $budi = Personel::where('nrp', '21960123456789')->firstOrFail();
        $this->assertSame('BRI', $budi->bank);
        $this->assertNotSame('', $budi->rekening);
        $this->assertSame('', Personel::where('nrp', '55555555')->value('bank'));
        $this->assertSame(1, Riwayat::where('aksi', 'impor: bank')->count());
    }

    public function test_bank_dari_nama_berkas_wajib(): void
    {
        $this->masuk()->post('/personel/impor/baru/bank', ['berkas' => [$this->xlsx('dpp/PEMBUKUAN_GAJI_BRI_BLN_OKTOBER_2026.xlsx', 'pembukuan.xlsx')]])
            ->assertSessionHasErrors('berkas');
        $this->assertSame(0, ImporPersonel::count());
    }

    public function test_berkas_bukan_nominatif_ditolak_dengan_pesan_python(): void
    {
        $this->masuk()->post('/personel/impor/baru/nominatif', ['berkas' => [$this->xlsx('impor/BUKAN_NOMINATIF.xlsx')]])
            ->assertSessionHasErrors('berkas');
        $this->assertSame(0, ImporPersonel::count());
    }

    public function test_validasi_dan_hak_akses(): void
    {
        $this->masuk('admin')->post('/personel/impor/baru/nominatif', ['berkas' => $this->nominatif()])->assertRedirect(route('akun.index'));
        $this->assertSame(0, ImporPersonel::count());
        $this->masuk()->post('/personel/impor/baru/nominatif', [])->assertSessionHasErrors('berkas');
        $this->post('/personel/impor/baru/nominatif', ['berkas' => [UploadedFile::fake()->create('a.csv', 1, 'text/csv')]])->assertSessionHasErrors('berkas.0');
        $this->post('/personel/impor/baru/ngawur', ['berkas' => $this->nominatif()])->assertNotFound();
        $this->get('/personel/impor')->assertOk()->assertSee('Impor dari Excel');

        $this->masuk()->post('/personel/impor/baru/nominatif', ['berkas' => $this->nominatif()]);
        $i = ImporPersonel::firstOrFail();
        $this->masuk('admin')->post("/personel/impor/{$i->id}/terapkan")->assertRedirect(route('akun.index'));
        $this->assertSame('pratinjau', $i->refresh()->status);
    }
}
