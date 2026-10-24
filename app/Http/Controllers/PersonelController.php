<?php

namespace App\Http\Controllers;

use App\Support\Demo;
use App\Support\Halaman;
use App\Support\ImporPersonel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PersonelController extends Controller
{
    public function index(Request $request)
    {
        $q = (string) $request->query('q', '');
        $jenis = (string) $request->query('jenis', '');
        $status = (string) $request->query('status', '');
        $list = Demo::saring(Demo::personelUrut(), $q, $jenis, $status, 'personel');
        [$hal, $galat] = Halaman::bagi($list, 25, $request);
        $agg = Demo::agg();

        return view('personel.index', compact('hal', 'galat', 'q', 'jenis', 'status') + [
            'jumlah' => ['' => count(Demo::all()), 'TNI' => $agg['A']['TNI']['n'], 'PNS' => $agg['A']['PNS']['n']],
        ]);
    }

    /* ---------- tambah / ubah ---------- */

    public function create()
    {
        return view('personel.form', ['r' => null, 'nilai' => $this->nilaiAwal(null)]);
    }

    public function edit(int $n)
    {
        $r = Demo::find($n);
        abort_unless($r, 404);

        return view('personel.form', ['r' => $r, 'nilai' => $this->nilaiAwal($r)]);
    }

    public function store(Request $request)
    {
        $o = $this->validasi($request, null);
        Demo::simpan(null, $o);

        return redirect()->route('personel.index')->with('ok', 'Data '.$o['nama'].' sudah disimpan.');
    }

    public function update(Request $request, int $n)
    {
        abort_unless(Demo::find($n), 404);
        $o = $this->validasi($request, $n);
        Demo::simpan($n, $o);

        return redirect()->route('personel.index')->with('ok', 'Data '.$o['nama'].' sudah disimpan.');
    }

    private function nilaiAwal(?array $r): array
    {
        return $r
            ? ['nama' => $r['nama'], 'jenis' => $r['jenis'], 'pangkat' => $r['pangkat'], 'nrp' => $r['nrp'],
                'bagian' => $r['bagian'] === '—' ? '' : $r['bagian'], 'kawin' => $r['kawin'] ? '1' : '0', 'anak' => (string) $r['anak']]
            : ['nama' => '', 'jenis' => 'TNI', 'pangkat' => '', 'nrp' => '', 'bagian' => '', 'kawin' => '0', 'anak' => '0'];
    }

    private function validasi(Request $request, ?int $n): array
    {
        $jenis = $request->input('jenis') === 'PNS' ? 'PNS' : 'TNI';
        $d = $request->validate([
            'nama' => ['required', 'string', 'max:100'],
            'jenis' => ['required', Rule::in(['TNI', 'PNS'])],
            'nrp' => ['required', 'string', 'max:30', function ($attr, $v, $fail) use ($n) {
                foreach (Demo::all() as $r) {
                    if ($r['n'] !== $n && $r['nrp'] === trim($v)) $fail('Nomor ini sudah dipakai oleh '.$r['nama'].'.');
                }
            }],
            'pangkat' => ['required', Rule::in(Demo::pangkat($jenis))],
            'bagian' => ['required', Rule::in(Demo::BAGIAN)],
            'kawin' => ['required', Rule::in(['0', '1'])],
            'anak' => ['nullable', 'integer', 'between:0,5'],
        ], [
            'nama.required' => 'Nama harus diisi.',
            'nrp.required' => 'NRP / NIP harus diisi.',
            'pangkat.required' => 'Pilih pangkat / golongan.',
            'pangkat.in' => 'Pilih pangkat / golongan.',
            'bagian.required' => 'Pilih bagian.',
            'bagian.in' => 'Pilih bagian.',
        ]);
        $kawin = $d['kawin'] === '1';

        return ['nama' => trim($d['nama']), 'jenis' => $d['jenis'], 'pangkat' => $d['pangkat'], 'nrp' => trim($d['nrp']),
            'bagian' => $d['bagian'], 'kawin' => $kawin, 'anak' => $kawin ? (int) ($d['anak'] ?? 0) : 0];
    }

    /* ---------- impor CSV ---------- */

    public function impor()
    {
        return view('personel.impor', ['hasil' => session('impor')]);
    }

    public function bacaImpor(Request $request)
    {
        if ($request->boolean('contoh')) {
            $hasil = ImporPersonel::baca('contoh-personel.csv', ImporPersonel::CONTOH);
        } else {
            $request->validate(['berkas' => ['required', 'file', 'max:5120']], [
                'berkas.required' => 'Pilih berkas CSV terlebih dahulu.',
                'berkas.max' => 'Ukuran berkas maksimal 5 MB.',
            ]);
            $f = $request->file('berkas');
            if (preg_match('/\.xlsx?$/i', $f->getClientOriginalName())) {
                $hasil = ['nama' => $f->getClientOriginalName(), 'galat' => 'Berkas Excel belum bisa dibaca langsung. Buka di Excel, pilih Simpan Sebagai → CSV, lalu pilih berkas CSV itu.'];
            } else {
                $hasil = ImporPersonel::baca($f->getClientOriginalName(), (string) file_get_contents($f->getRealPath()));
            }
        }
        session(['impor' => $hasil]);

        return redirect()->route('personel.impor');
    }

    public function terapkanImpor(Request $request)
    {
        $hasil = session('impor');
        $n = 0;
        foreach ($hasil['rows'] ?? [] as $r) {
            if ($r['masalah'] !== '') continue;
            Demo::simpan(null, ['nama' => $r['nama'], 'jenis' => $r['jenis'], 'pangkat' => $r['pangkat'], 'nrp' => $r['nrp'],
                'bagian' => $r['bagian'], 'anak' => $r['anak'], 'kawin' => $r['anak'] > 0]);
            $n++;
        }
        $request->session()->forget('impor');

        return redirect()->route('personel.index')->with('ok', $n.' personel ditambahkan.');
    }

    public function templat()
    {
        return response()->streamDownload(function () {
            echo "\xEF\xBB\xBF".ImporPersonel::TEMPLAT;
        }, 'templat-personel.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
