<?php

namespace Tests\Feature;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Peran Admin: hanya kelola akun dan log aktivitas. Plus identitas aplikasi (nama, ikon tab). */
class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function sebagai(string $peran = 'admin', array $lain = []): User
    {
        $u = User::factory()->create($lain + ['role' => $peran, 'name' => 'Pak '.$peran, 'username' => $peran.'1', 'aktif' => true]);
        $this->actingAs($u);

        return $u;
    }

    private function akunBaru(array $ganti = []): array
    {
        return $ganti + ['name' => 'Sersan Budi', 'username' => 'budi.s', 'role' => 'juru_bayar', 'sandi' => 'rahasia-123', 'sandi_confirmation' => 'rahasia-123'];
    }

    public function test_admin_masuk_ke_kelola_akun_dan_halaman_kerja_dialihkan(): void
    {
        User::factory()->create(['username' => 'adm', 'role' => 'admin', 'aktif' => true, 'password' => 'sandi-sandi']);

        $this->post('/masuk', ['pengguna' => 'adm', 'sandi' => 'sandi-sandi'])->assertRedirect(route('akun.index'));
        foreach (['/', '/dashboard', '/gaji', '/slip', '/rekap', '/personel', '/potongan', '/pengaturan'] as $url) {
            $this->get($url)->assertRedirect(route('akun.index'));
        }
    }

    public function test_menu_admin_hanya_akun_dan_log(): void
    {
        $this->sebagai();

        $this->get('/akun')->assertOk()->assertSee('Akun Pengguna')->assertSee('Log Aktivitas')->assertSee('Admin')
            ->assertDontSee('Proses Gaji')->assertDontSee('Data Personel')->assertDontSee('Pengaturan')->assertDontSee('Rekap');
    }

    public function test_peran_lain_tidak_boleh_masuk_halaman_admin(): void
    {
        $this->sebagai('juru_bayar');
        foreach (['/akun', '/akun/baru', '/log'] as $url) $this->get($url)->assertForbidden();
        $this->post('/akun', $this->akunBaru())->assertForbidden();
        $this->assertSame(1, User::count());
    }

    public function test_menu_juru_bayar_tidak_menampilkan_menu_admin_dan_memuat_nama_lengkap(): void
    {
        $this->sebagai('juru_bayar');

        $this->get('/dashboard')->assertOk()->assertSee('Sistem Pengolahan Data dan Slip Gaji')->assertSee('Juru Bayar')
            ->assertDontSee('Akun Pengguna')->assertDontSee('Log Aktivitas')->assertDontSee('Slip Gaji Terpadu');
    }

    public function test_ikon_tab_memakai_logo_instansi(): void
    {
        $this->get('/masuk')->assertOk()->assertSee('rel="icon"', false)->assertSee('img/logo-instansi.png', false);
        $this->sebagai('juru_bayar');
        $this->get('/dashboard')->assertSee('rel="icon"', false)->assertSee('img/logo-instansi.png', false);
    }

    public function test_admin_membuat_akun_yang_bisa_dipakai_masuk(): void
    {
        $this->sebagai();

        $this->post('/akun', $this->akunBaru())->assertRedirect(route('akun.index'));
        $u = User::where('username', 'budi.s')->firstOrFail();
        $this->assertSame('juru_bayar', $u->role);
        $this->assertTrue($u->aktif);
        $this->assertNotSame('rahasia-123', $u->password);       // tersimpan sebagai hash

        $this->post('/keluar');
        $this->post('/masuk', ['pengguna' => 'budi.s', 'sandi' => 'rahasia-123'])->assertRedirect(route('dashboard'));
    }

    public function test_halaman_ubah_akun_tidak_merusak_ukuran_ikon_menu(): void
    {
        $admin = $this->sebagai();

        // Variabel $u di view anak bocor ke partial ikon (ukuran ikon) bila dipakai untuk model akun.
        $html = $this->get("/akun/{$admin->id}/ubah")->assertOk()->getContent();
        preg_match('/<svg[^>]*?\swidth="(?!\d+")[^"]*"/', $html, $m); $this->assertSame([], $m);
        $this->assertStringContainsString('<svg aria-hidden="true" width="18" height="18"', $html);
    }

    public function test_validasi_akun(): void
    {
        $this->sebagai();
        User::factory()->create(['username' => 'dipakai']);

        $this->post('/akun', $this->akunBaru(['username' => 'dipakai']))->assertSessionHasErrors('username');
        $this->post('/akun', $this->akunBaru(['username' => 'ada spasi']))->assertSessionHasErrors('username');
        $this->post('/akun', $this->akunBaru(['role' => 'raja']))->assertSessionHasErrors('role');
        $this->post('/akun', $this->akunBaru(['role' => 'operator']))->assertSessionHasErrors('role');      // peran Operator sudah dihapus
        $this->post('/akun', $this->akunBaru(['sandi' => 'pendek', 'sandi_confirmation' => 'pendek']))->assertSessionHasErrors('sandi');
        $this->post('/akun', $this->akunBaru(['sandi_confirmation' => 'beda-sekali']))->assertSessionHasErrors('sandi');
        $this->assertSame(2, User::count());
    }

    public function test_ubah_akun_ganti_sandi_peran_dan_nonaktifkan(): void
    {
        $this->sebagai();
        $u = User::factory()->create(['username' => 'ani', 'role' => 'juru_bayar', 'aktif' => true, 'password' => 'sandi-lama-1']);

        $this->put("/akun/{$u->id}", ['name' => 'Ani Baru', 'username' => 'ani', 'role' => 'admin', 'sandi' => 'sandi-baru-22', 'sandi_confirmation' => 'sandi-baru-22'])
            ->assertRedirect(route('akun.index'));
        $u->refresh();
        $this->assertSame(['Ani Baru', 'admin', false], [$u->name, $u->role, $u->aktif]);   // kotak "aktif" tidak dicentang -> nonaktif
        $this->assertTrue(\Hash::check('sandi-baru-22', $u->password));

        $this->put("/akun/{$u->id}", ['name' => 'Ani Baru', 'username' => 'ani', 'role' => 'admin', 'aktif' => '1'])->assertRedirect();
        $this->assertTrue($u->refresh()->aktif);
        $this->assertTrue(\Hash::check('sandi-baru-22', $u->password));      // sandi kosong = tidak diganti
    }

    public function test_admin_tidak_bisa_menurunkan_atau_menonaktifkan_diri_sendiri(): void
    {
        $admin = $this->sebagai();

        $this->put("/akun/{$admin->id}", ['name' => 'Admin', 'username' => 'admin1', 'role' => 'juru_bayar'])->assertRedirect();
        $admin->refresh();
        $this->assertSame('admin', $admin->role);
        $this->assertTrue($admin->aktif);
    }

    public function test_akun_yang_dinonaktifkan_langsung_dikeluarkan_dan_tidak_bisa_masuk(): void
    {
        $this->sebagai('juru_bayar', ['username' => 'jb']);
        User::where('username', 'jb')->update(['aktif' => false]);
        $this->app['auth']->forgetGuards();

        $this->get('/dashboard')->assertRedirect(route('masuk'));
        $this->assertGuest();
        $this->post('/masuk', ['pengguna' => 'jb', 'sandi' => 'password'])->assertSessionHasErrors('sandi');
    }

    public function test_log_mencatat_masuk_gagal_masuk_dan_keluar_tanpa_kata_sandi(): void
    {
        User::factory()->create(['username' => 'op', 'role' => 'juru_bayar', 'aktif' => true, 'password' => 'sandi-benar-1', 'name' => 'Pak Op']);

        $this->post('/masuk', ['pengguna' => 'op', 'sandi' => 'salah-total']);
        $this->post('/masuk', ['pengguna' => 'op', 'sandi' => 'sandi-benar-1']);
        $this->post('/keluar');

        $this->assertSame(['masuk_gagal', 'masuk', 'keluar'], LogAktivitas::orderBy('id')->pluck('aksi')->all());
        $gagal = LogAktivitas::where('aksi', 'masuk_gagal')->first();
        $this->assertSame('op', $gagal->username);
        $this->assertSame('Pak Op', LogAktivitas::where('aksi', 'masuk')->first()->pengguna);
        $this->assertStringNotContainsString('salah-total', json_encode(LogAktivitas::all()));
        $this->assertStringNotContainsString('sandi-benar-1', json_encode(LogAktivitas::all()));
    }

    public function test_pekerjaan_dan_perubahan_akun_tercatat_di_log_yang_dibaca_admin(): void
    {
        $this->sebagai('juru_bayar', ['name' => 'Juru Satu']);
        $this->post('/personel', ['jenis' => 'TNI', 'nama' => 'Ahmad Fauzi', 'nrp_asli' => '11112214', 'status' => 'aktif'])->assertRedirect();
        $this->assertSame('personel_tambah', LogAktivitas::latest('id')->first()->aksi);

        $this->sebagai();
        $this->post('/akun', $this->akunBaru());
        $this->put('/akun/'.User::where('username', 'budi.s')->value('id'), ['name' => 'Sersan Budi', 'username' => 'budi.s', 'role' => 'admin', 'aktif' => '1']);

        $this->get('/log')->assertOk()->assertSee('Personel ditambah')->assertSee('Ahmad Fauzi')->assertSee('Juru Satu')
            ->assertSee('Akun ditambah')->assertSee('Akun diubah')->assertSee('peran Juru Bayar → Admin');
        $this->get('/log?aksi=personel_tambah')->assertOk()->assertSee('Ahmad Fauzi')->assertDontSee('peran Juru Bayar');
        $this->get('/log?q=budi')->assertOk()->assertSee('Sersan Budi')->assertDontSee('Ahmad Fauzi');
        $this->get('/log?aksi=ngawur')->assertOk()->assertSee('Ahmad Fauzi');          // aksi tak dikenal diabaikan
    }

    public function test_log_tidak_bisa_diubah_atau_dihapus_walau_lewat_sql(): void
    {
        $this->sebagai();
        LogAktivitas::catat('masuk');

        try {
            DB::table('log_aktivitas')->update(['keterangan' => 'dipalsukan']);
            $this->fail('UPDATE seharusnya ditolak');
        } catch (QueryException) {
            $this->assertTrue(true);
        }
        $this->expectException(QueryException::class);
        DB::table('log_aktivitas')->delete();
    }
}
