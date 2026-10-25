<?php

namespace Tests\Feature;

use App\Models\Personel;
use App\Models\Riwayat;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PersonelTest extends TestCase
{
    use RefreshDatabase;

    private function sebagai(string $peran = 'juru_bayar'): static
    {
        return $this->actingAs(User::factory()->create(['role' => $peran, 'name' => 'Pak '.$peran]));
    }

    private function data(array $o = []): array
    {
        return $o + ['jenis' => 'TNI', 'nama' => 'Ahmad Fauzi', 'nrp_asli' => '11.2214.0001', 'pangkat' => 'MAYOR INF',
            'jabatan' => 'Pasi Pers', 'status' => 'aktif'];
    }

    public function test_tambah_personel_menyimpan_nrp_angka_saja_dan_mencatat_riwayat(): void
    {
        $this->sebagai('juru_bayar')->post('/personel', $this->data(['bank' => 'bri', 'rekening' => '1234-5678 90']))
            ->assertRedirect(route('personel.index'));

        $p = Personel::firstOrFail();
        $this->assertSame('1122140001', $p->nrp);
        $this->assertSame('11.2214.0001', $p->nrp_asli);
        $this->assertSame('BRI', $p->bank);
        $this->assertSame('1234567890', $p->rekening);

        $h = Riwayat::firstOrFail();
        $this->assertSame('tambah', $h->aksi);
        $this->assertSame('Pak juru_bayar', $h->operator);
        $this->assertSame(['', 'Ahmad Fauzi'], $h->perubahan['nama']);
    }

    public function test_nrp_ganda_ditolak_walau_beda_tulisan(): void
    {
        $this->sebagai()->post('/personel', $this->data());

        $this->post('/personel', $this->data(['nama' => 'Orang Lain', 'nrp_asli' => '1122140001']))
            ->assertSessionHasErrors(['nrp_asli' => 'Nomor ini sudah dipakai oleh Ahmad Fauzi.']);
        $this->assertSame(1, Personel::count());
    }

    public function test_validasi_kolom(): void
    {
        $this->sebagai()->post('/personel', ['jenis' => 'ABC', 'nama' => '', 'nrp_asli' => '12', 'bank' => 'XYZ', 'rekening' => 'abc'])
            ->assertSessionHasErrors(['jenis', 'nama', 'nrp_asli', 'bank', 'rekening']);

        $this->post('/personel', $this->data(['jenis' => 'PNS', 'nrp_asli' => '123']))
            ->assertSessionHasErrors(['nrp_asli' => 'NIP harus berisi minimal 5 angka.']);

        $this->post('/personel', $this->data(['status' => 'keluar']))
            ->assertSessionHasErrors(['tgl_keluar', 'alasan_keluar']);
        $this->assertSame(0, Personel::count());
    }

    public function test_ubah_dicatat_kolom_demi_kolom_dan_tanpa_perubahan_tidak_mencatat(): void
    {
        $this->sebagai()->post('/personel', $this->data());
        $p = Personel::firstOrFail();

        $this->put("/personel/{$p->id}", $this->data(['jabatan' => 'Kasi Pers']))->assertRedirect(route('personel.index'));
        $h = Riwayat::latest('id')->first();
        $this->assertSame('ubah', $h->aksi);
        $this->assertSame(['jabatan' => ['Pasi Pers', 'Kasi Pers']], $h->perubahan);

        $n = Riwayat::count();
        $this->put("/personel/{$p->id}", $this->data(['jabatan' => 'Kasi Pers']));
        $this->assertSame($n, Riwayat::count());
    }

    public function test_keluar_dan_aktif_kembali(): void
    {
        $this->sebagai()->post('/personel', $this->data());
        $p = Personel::firstOrFail();

        $this->put("/personel/{$p->id}", $this->data(['status' => 'keluar', 'tgl_keluar' => '2026-10-01', 'alasan_keluar' => 'Pindah satuan']));
        $this->assertSame('keluar', Riwayat::latest('id')->first()->aksi);
        $this->assertSame('2026-10-01', $p->refresh()->tgl_keluar->format('Y-m-d'));

        $this->put("/personel/{$p->id}", $this->data(['status' => 'aktif', 'tgl_keluar' => '2026-10-01', 'alasan_keluar' => 'x']));
        $p->refresh();
        $this->assertSame('aktif kembali', Riwayat::latest('id')->first()->aksi);
        $this->assertNull($p->tgl_keluar);
        $this->assertSame('', $p->alasan_keluar);
    }

    public function test_hanya_juru_bayar_boleh_menghapus(): void
    {
        $this->sebagai()->post('/personel', $this->data());
        $p = Personel::firstOrFail();

        $this->sebagai('admin')->delete("/personel/{$p->id}")->assertRedirect(route('akun.index'));
        $this->assertSame(1, Personel::count());

        $this->sebagai('juru_bayar')->delete("/personel/{$p->id}")->assertRedirect(route('personel.index'));
        $this->assertSame(0, Personel::count());
        $h = Riwayat::latest('id')->first();
        $this->assertSame('hapus', $h->aksi);
        $this->assertSame(['Ahmad Fauzi', ''], $h->perubahan['nama']);
    }

    public function test_pengurutan_daftar_personel(): void
    {
        $this->sebagai();
        $n = 0;
        foreach ([['TNI', 'Charlie', 'Mayor Caj (K)'], ['TNI', 'bravo', 'Prada'], ['PNS', 'Delta', 'Penata Muda Tk. I III/b'], ['TNI', 'Alfa', 'Kolonel Caj'],
            ['PNS', 'Echo', 'Pembina IV/a'], ['TNI', 'Foxtrot', ''], ['PNS', 'Golf', 'Pengatur II/c'], ['TNI', 'Hotel', 'Serka (K)']] as [$jenis, $nama, $pangkat]) {
            Personel::create(['jenis' => $jenis, 'nama' => $nama, 'pangkat' => $pangkat, 'nrp' => (string) (++$n), 'nrp_asli' => (string) $n]);
        }
        $urutan = fn (string $query) => collect($this->get('/personel'.$query)->assertOk()->viewData('hal')->items())->pluck('nama')->all();

        // bawaan = jenis keanggotaan (TNI dulu), lalu nama tanpa membedakan huruf besar/kecil
        $this->assertSame(['Alfa', 'bravo', 'Charlie', 'Foxtrot', 'Hotel', 'Delta', 'Echo', 'Golf'], $urutan(''));
        $this->assertSame(['Delta', 'Echo', 'Golf', 'Alfa', 'bravo', 'Charlie', 'Foxtrot', 'Hotel'], $urutan('?urut=jenis&arah=turun'));
        $this->assertSame(['Alfa', 'bravo', 'Charlie', 'Delta', 'Echo', 'Foxtrot', 'Golf', 'Hotel'], $urutan('?urut=nama'));
        $this->assertSame(['Hotel', 'Golf', 'Foxtrot', 'Echo', 'Delta', 'Charlie', 'bravo', 'Alfa'], $urutan('?urut=nama&arah=turun'));
        // pangkat: tertinggi dulu secara bawaan; TNI dan PNS tetap dipisah; pangkat kosong di paling bawah kelompoknya
        $this->assertSame(['Alfa', 'Charlie', 'Hotel', 'bravo', 'Foxtrot', 'Echo', 'Delta', 'Golf'], $urutan('?urut=pangkat'));
        $this->assertSame(['bravo', 'Hotel', 'Charlie', 'Alfa', 'Foxtrot', 'Golf', 'Delta', 'Echo'], $urutan('?urut=pangkat&arah=naik'));
        $this->assertSame(['Alfa', 'Charlie', 'Hotel', 'bravo', 'Foxtrot'], $urutan('?urut=pangkat&jenis=TNI'));
        $this->assertSame($urutan(''), $urutan('?urut=ngawur&arah=sembarang'));      // nilai tak dikenal diabaikan

        $this->get('/personel?urut=pangkat')->assertSee('Urutkan')->assertSee('Tertinggi dulu');
        $this->get('/personel?urut=nama')->assertSee('A → Z');
    }

    public function test_judul_kolom_bisa_diklik_untuk_mengurutkan(): void
    {
        $this->sebagai();
        Personel::create(['jenis' => 'TNI', 'nama' => 'Alfa', 'nrp' => '1', 'nrp_asli' => '1']);

        // bawaan: jenis naik. Kolom lain dimulai dari arah bawaannya (pangkat: tertinggi dulu), halaman kembali ke 1
        $h = $this->get('/personel?halaman=1')->assertOk()->assertSee('aria-sort="ascending"', false)->getContent();
        $this->assertSame(1, substr_count($h, 'aria-sort='));
        $this->assertMatchesRegularExpression('~<th scope="col" class=""\s+aria-sort="ascending"\s*>\s*<a href="[^"]*urut=jenis&amp;arah=turun~', $h);   // klik lagi = dibalik
        $this->assertStringContainsString('urut=nama&amp;arah=naik', $h);
        $this->assertStringContainsString('urut=pangkat&amp;arah=turun', $h);
        $this->assertStringNotContainsString('halaman=', preg_replace('~<form[^>]*>.*?</form>~s', '', $h));      // tautan judul kolom tidak membawa nomor halaman

        // kolom yang sedang aktif dibalik arahnya, dan penanda aria-sort ikut berpindah
        $h = $this->get('/personel?urut=nama&arah=naik')->getContent();
        $this->assertMatchesRegularExpression('~aria-sort="ascending"\s*>\s*<a href="[^"]*urut=nama&amp;arah=turun~', $h);
        $h = $this->get('/personel?urut=pangkat')->assertSee('aria-sort="descending"', false)->getContent();
        $this->assertMatchesRegularExpression('~aria-sort="descending"\s*>\s*<a href="[^"]*urut=pangkat&amp;arah=naik~', $h);
    }

    public function test_peringkat_pangkat_dan_golongan(): void
    {
        $p = fn (string $j, string $t) => \App\Support\Pangkat::peringkat($j, $t);

        $this->assertTrue($p('TNI', 'Prada') < $p('TNI', 'Serda') && $p('TNI', 'Peltu') < $p('TNI', 'Letda') && $p('TNI', 'Kolonel Caj') < $p('TNI', 'Brigjen TNI'));
        $this->assertSame($p('TNI', 'Kapten Caj'), $p('TNI', 'KAPTEN CAJ (K)'));
        $this->assertTrue($p('PNS', 'Pengatur II/c') < $p('PNS', 'Penata Muda Tk. I III/b') && $p('PNS', 'Penata III/c') < $p('PNS', 'Pembina IV/a'));
        $this->assertSame(32, $p('PNS', 'Pengatur Tk. I II/d') + 8);      // II/d = 24
        $this->assertNull($p('TNI', ''));
        $this->assertNull($p('TNI', 'Entah'));
        $this->assertNull($p('PNS', 'Penata'));
    }

    public function test_halaman_riwayat_memuat_semua_jenis_catatan(): void
    {
        $u = $this->sebagai('juru_bayar');
        $this->post('/personel', $this->data());
        $baris = fn (string $aksi, array $perubahan) => Riwayat::create(['operator' => 'Pak X', 'aksi' => $aksi, 'nrp' => '', 'nama' => '', 'perubahan' => $perubahan]);
        // Ringkasan impor berisi nilai biasa, bukan pasangan [lama, baru] (dulu membuat halaman ini error)
        $baris('impor', ['berkas' => ['A NOMINATIF.xlsx', 'B NOMINATIF.xlsx'], 'jenis' => 'nominatif', 'tambah' => 178, 'ubah' => 0]);
        $baris('impor', ['berkas' => ['SATU.xlsx'], 'jenis' => 'bank', 'tambah' => 0]);
        $baris('proses: hapus', ['Proses gaji' => ['#1 NOVEMBER 2026 · selesai', '']]);

        $this->get('/personel/riwayat')->assertOk()->assertSee('A NOMINATIF.xlsx, B NOMINATIF.xlsx')->assertSee('nominatif')->assertSee('178')
            ->assertSee('SATU.xlsx')->assertSee('#1 NOVEMBER 2026 · selesai')->assertSee('Ahmad Fauzi');
    }

    public function test_riwayat_tidak_bisa_diubah_atau_dihapus_walau_lewat_sql(): void
    {
        $this->sebagai()->post('/personel', $this->data());

        $this->expectException(QueryException::class);
        DB::table('riwayat')->update(['nama' => 'dipalsukan']);
    }

    public function test_riwayat_tidak_bisa_dihapus_lewat_sql(): void
    {
        $this->sebagai()->post('/personel', $this->data());

        $this->expectException(QueryException::class);
        DB::table('riwayat')->delete();
    }

    public function test_daftar_menyaring_dan_menampilkan(): void
    {
        $this->sebagai();
        Personel::create(['jenis' => 'TNI', 'nama' => 'Budi Santoso', 'nrp' => '11111', 'nrp_asli' => '11111', 'bank' => 'BRI']);
        Personel::create(['jenis' => 'PNS', 'nama' => 'Dewi Anggraini', 'nrp' => '198201010001', 'nrp_asli' => '198201010001']);
        Personel::create(['jenis' => 'TNI', 'nama' => 'Eko Keluar', 'nrp' => '22222', 'nrp_asli' => '22222', 'status' => 'keluar', 'tgl_keluar' => '2026-09-01']);

        $this->get('/personel')->assertOk()->assertSee('Budi Santoso')->assertSee('Dewi Anggraini');
        $this->get('/personel?jenis=PNS')->assertOk()->assertSee('Dewi Anggraini')->assertDontSee('Budi Santoso');
        $this->get('/personel?q=198201')->assertOk()->assertSee('Dewi Anggraini')->assertDontSee('Budi Santoso');
        $this->get('/personel?status=keluar')->assertOk()->assertSee('Eko Keluar')->assertDontSee('Budi Santoso');
        $this->get('/personel?status=tanpa_bank')->assertOk()->assertSee('Dewi Anggraini')->assertDontSee('Budi Santoso')->assertDontSee('Eko Keluar');
        $this->get('/personel/riwayat')->assertOk();
        $this->get('/personel/baru')->assertOk();
        $this->get('/personel/'.Personel::first()->id.'/ubah')->assertOk()->assertSee('Budi Santoso');
    }
}
