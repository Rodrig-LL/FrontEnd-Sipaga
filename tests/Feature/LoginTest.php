<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    private function pengguna(array $o = []): User
    {
        return User::factory()->create($o + ['username' => 'budi', 'password' => 'sandi-rahasia-1']);
    }

    public function test_tamu_diarahkan_ke_halaman_masuk(): void
    {
        $this->get('/dashboard')->assertRedirect(route('masuk'));
        $this->get('/personel')->assertRedirect(route('masuk'));
    }

    public function test_url_lama_dasbor_dialihkan_ke_dashboard(): void
    {
        $this->get('/dasbor')->assertRedirect('/dashboard')->assertStatus(301);
    }

    public function test_halaman_masuk_menampilkan_identitas_instansi_dan_aplikasi(): void
    {
        $this->get('/masuk')->assertOk()
            ->assertSee('Direktorat Ajudan Jenderal')->assertSee('TNI Angkatan Darat')
            ->assertSee('SIPAGA')->assertSee('Sistem Pengolahan Data dan Slip Gaji')
            ->assertSee('alt="Lambang Vijaya Kusuma"', false)->assertSee('Nama pengguna');
    }

    public function test_masuk_dengan_sandi_benar(): void
    {
        $this->pengguna();

        $this->post('/masuk', ['pengguna' => 'budi', 'sandi' => 'sandi-rahasia-1'])->assertRedirect(route('dashboard'));
        $this->assertAuthenticated();
    }

    public function test_sandi_salah_atau_pengguna_tak_dikenal_ditolak_dengan_pesan_sama(): void
    {
        $this->pengguna();

        $a = $this->from('/masuk')->post('/masuk', ['pengguna' => 'budi', 'sandi' => 'salah']);
        $b = $this->from('/masuk')->post('/masuk', ['pengguna' => 'tidak-ada', 'sandi' => 'apa-saja']);

        $a->assertSessionHasErrors(['sandi' => 'Nama pengguna atau kata sandi salah.']);
        $b->assertSessionHasErrors(['sandi' => 'Nama pengguna atau kata sandi salah.']);
        $this->assertGuest();
    }

    public function test_akun_nonaktif_tidak_bisa_masuk(): void
    {
        $this->pengguna(['aktif' => false]);

        $this->post('/masuk', ['pengguna' => 'budi', 'sandi' => 'sandi-rahasia-1'])->assertSessionHasErrors('sandi');
        $this->assertGuest();
    }

    public function test_kata_sandi_tidak_tersimpan_polos(): void
    {
        $u = $this->pengguna();

        $this->assertNotSame('sandi-rahasia-1', $u->getRawOriginal('password'));
    }

    public function test_keluar(): void
    {
        $this->actingAs($this->pengguna())->post('/keluar')->assertRedirect(route('masuk'));
        $this->assertGuest();
    }

    public function test_percobaan_berulang_dibatasi(): void
    {
        $this->pengguna();
        for ($i = 0; $i < 5; $i++) $this->post('/masuk', ['pengguna' => 'budi', 'sandi' => 'salah']);

        $this->post('/masuk', ['pengguna' => 'budi', 'sandi' => 'sandi-rahasia-1'])->assertStatus(429);
    }
}
