<?php

namespace App\Http\Controllers;

use App\Support\Demo;
use App\Support\Halaman;
use Illuminate\Http\Request;

/**
 * Proses gaji bertahap: 5 langkah, masing-masing satu URL (/proses/1 … /proses/5).
 * Keputusan operator disimpan di session('proses'); setiap klik langsung tersimpan,
 * jadi halaman boleh ditutup lalu dilanjutkan. Pekerjaan berat (baca softfile,
 * pencocokan, pembuatan PDF) nantinya dijalankan di queue server.
 */
class ProsesController extends Controller
{
    private const JUDUL = ['Unggah softfile', 'Periksa hasil baca', 'Cocokkan dengan data personel', 'Verifikasi selisih', 'Terbitkan slip'];
    private const LABEL = ['Unggah softfile', 'Periksa hasil baca', 'Cocokkan data', 'Verifikasi selisih', 'Terbitkan slip'];
    private const BANTUAN = [
        'Unggah softfile gaji TNI dan PNS untuk periode ini.',
        'Pastikan sistem membaca berkas dengan benar sebelum lanjut.',
        'Sistem sudah mencocokkan NRP/NIP. Selesaikan data yang tidak ditemukan.',
        'Pilih angka yang benar untuk setiap komponen yang berubah.',
        'Periksa ringkasan, lalu terbitkan slip.',
    ];

    private function state(): array
    {
        return session('proses', []) + ['fixed' => [], 'choice' => [], 'published' => false, 'files' => []];
    }

    public function index()
    {
        return redirect()->route('proses.show', 1);
    }

    public function show(Request $request, int $langkah)
    {
        abort_unless($langkah >= 1 && $langkah <= 5, 404);
        $st = $this->state();
        $fix = count($st['fixed']);
        $putus = count($st['choice']);

        $data = [
            'langkah' => $langkah, 'judul' => self::JUDUL[$langkah - 1], 'bantuan' => self::BANTUAN[$langkah - 1],
            'label' => self::LABEL, 'st' => $st, 'fix' => $fix, 'putus' => $putus,
            'peringatan' => '',
        ];
        if ($langkah === 3 && $fix < 7) $data['peringatan'] = (7 - $fix).' data belum selesai — slipnya akan ditunda.';
        if ($langkah === 4 && $putus < 19) $data['peringatan'] = (19 - $putus).' selisih belum diputuskan — slipnya akan ditunda.';
        if ($langkah === 3) $data['tidakCocok'] = Demo::tidakCocok();
        if ($langkah === 4) {
            [$data['hal'], $data['galat']] = Halaman::bagi(Demo::selisih(), 8, $request);
        }
        if ($langkah === 5) {
            $data['ditunda'] = (19 - $putus) + (7 - $fix);
            $data['terbit'] = 486 + $putus + $fix;
        }

        return view('proses.show', $data);
    }

    /** Satu pintu untuk semua klik di wizard. */
    public function aksi(Request $request)
    {
        $st = $this->state();
        $do = $request->input('do');
        $key = (string) $request->input('key');

        if ($do === 'fix') $st['fixed'][$key] = true;
        elseif ($do === 'unfix') unset($st['fixed'][$key]);
        elseif ($do === 'baru' || $do === 'lama') $st['choice'][$key] = $do;   // pilih angka softfile / angka lama
        elseif ($do === 'batal') unset($st['choice'][$key]);                    // ubah keputusan
        elseif ($do === 'allnew') {
            [$hal] = Halaman::bagi(Demo::selisih(), 8, $request);
            foreach ($hal as $r) $st['choice'][$r['key']] = 'baru';
        } elseif ($do === 'publish') $st['published'] = true;
        elseif ($do === 'reset') $st = ['fixed' => [], 'choice' => [], 'published' => false, 'files' => []];
        elseif ($do === 'upload') {
            foreach (['tni', 'pns'] as $j) {
                if ($request->hasFile($j)) $st['files'][$j] = $request->file($j)->getClientOriginalName();
            }
        }
        $request->session()->put('proses', $st);

        // Pada "reset" kembali ke langkah 1; selain itu tetap di halaman yang sama (termasuk nomor halamannya).
        return $do === 'reset' ? redirect()->route('proses.show', 1) : back();
    }
}
