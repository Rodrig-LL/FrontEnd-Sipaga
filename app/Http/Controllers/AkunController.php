<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kelola akun pengguna (khusus Admin). Akun tidak dihapus, hanya dinonaktifkan, supaya jejaknya di log tetap jelas. */
class AkunController extends Controller
{
    public function index()
    {
        return view('akun.index', ['daftar' => User::orderByDesc('aktif')->orderBy('role')->orderBy('name')->get()]);
    }

    public function create()
    {
        return view('akun.form', ['akun' => null]);
    }

    public function store(Request $request)
    {
        $d = $request->validate($this->aturan(null) + ['sandi' => ['required', 'string', 'min:8', 'confirmed']], $this->pesan());
        $u = User::create(['name' => trim($d['name']), 'username' => $d['username'], 'role' => $d['role'], 'password' => $d['sandi'], 'aktif' => true]);
        LogAktivitas::catat('akun_tambah', "{$u->name} ({$u->username}), peran ".User::PERAN[$u->role]);

        return redirect()->route('akun.index')->with('ok', 'Akun '.$u->name.' dibuat.');
    }

    public function edit(User $akun)
    {
        return view('akun.form', ['akun' => $akun]);
    }

    public function update(Request $request, User $akun)
    {
        $d = $request->validate($this->aturan($akun) + ['sandi' => ['nullable', 'string', 'min:8', 'confirmed']], $this->pesan());
        $diri = $akun->is($request->user());

        // Admin tidak boleh menurunkan atau menonaktifkan akunnya sendiri; dengan begitu selalu ada minimal satu Admin aktif.
        $baru = ['name' => trim($d['name']), 'username' => $d['username'],
            'role' => $diri ? $akun->role : $d['role'], 'aktif' => $diri ? true : $request->boolean('aktif')];
        $ubah = [];
        foreach ($baru as $k => $v) {
            if ($akun->$k !== $v) $ubah[] = match ($k) {
                'name' => 'nama', 'username' => 'nama pengguna',
                'role' => 'peran '.User::PERAN[$akun->role].' → '.User::PERAN[$v],
                'aktif' => $v ? 'diaktifkan' : 'dinonaktifkan',
            };
        }
        if (! empty($d['sandi'])) $ubah[] = 'kata sandi diganti';

        $akun->fill($baru);
        if (! empty($d['sandi'])) $akun->password = $d['sandi'];
        $akun->save();
        if ($ubah) LogAktivitas::catat('akun_ubah', "{$akun->name} ({$akun->username}): ".implode(', ', $ubah));

        return redirect()->route('akun.index')->with('ok', $ubah ? 'Akun '.$akun->name.' disimpan.' : 'Tidak ada yang berubah.');
    }

    /** Hapus permanen. Namanya tetap tertulis di riwayat dan log (disimpan sebagai teks saat kejadian). */
    public function destroy(Request $request, User $akun)
    {
        if ($akun->is($request->user())) {
            return redirect()->route('akun.edit', $akun)->withErrors(['hapus' => 'Anda tidak bisa menghapus akun Anda sendiri.']);
        }

        $ringkas = "{$akun->name} ({$akun->username}), peran ".(User::PERAN[$akun->role] ?? $akun->role);
        $akun->delete();
        LogAktivitas::catat('akun_hapus', $ringkas);

        return redirect()->route('akun.index')->with('ok', 'Akun '.$akun->name.' dihapus.');
    }

    private function aturan(?User $akun): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'max:60', 'regex:/^[A-Za-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($akun?->id)],
            'role' => ['required', Rule::in(array_keys(User::PERAN))],
        ];
    }

    private function pesan(): array
    {
        return [
            'name.required' => 'Isi nama lengkap.',
            'username.required' => 'Isi nama pengguna.',
            'username.regex' => 'Nama pengguna hanya boleh huruf, angka, titik, garis bawah, dan tanda hubung.',
            'username.unique' => 'Nama pengguna itu sudah dipakai.',
            'role.required' => 'Pilih peran.',
            'role.in' => 'Peran tidak dikenal.',
            'sandi.required' => 'Isi kata sandi.',
            'sandi.min' => 'Kata sandi minimal 8 karakter.',
            'sandi.confirmed' => 'Ulangi kata sandi dengan isian yang sama.',
        ];
    }
}
