<?php

namespace App\Http\Controllers;

use App\Jobs\JalankanProsesGaji;
use App\Models\Personel;
use App\Models\ProsesGaji;
use App\Models\Riwayat;
use App\Support\DataGaji;
use App\Support\Halaman;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/** Proses gaji sungguhan: unggah DPP + pembukuan lalu, Python yang bekerja (lewat JembatanPython), hasilnya dibaca di sini. */
class ProsesGajiController extends Controller
{
    private const BATAS_PDF_KB = 81920;     // 80 MB, sama dengan aplikasi asli

    public function index()
    {
        return view('gaji.index', [
            'daftar' => ProsesGaji::with('user')->latest('id')->limit(20)->get(), 'aktif' => DataGaji::aktif(),
            'pythonAda' => is_file(config('sipaga.python')),
            'bolehJalan' => Gate::allows('jalankan-proses'),
            'tniAktif' => Personel::aktif()->where('jenis', 'TNI')->count(),
        ]);
    }

    public function store(Request $request)
    {
        Gate::authorize('jalankan-proses');

        $request->merge(['bulan' => strtoupper(trim((string) $request->input('bulan')))]);
        $request->validate([
            'pdf' => ['required', 'array', 'min:1', 'max:40'],
            'pdf.*' => ['file', 'mimes:pdf', 'max:'.self::BATAS_PDF_KB],
            'buku' => ['required', 'file', 'mimes:xlsx', 'max:20480'],
            'bulan' => ['nullable', 'regex:/^[A-Z]+ \d{4}$/'],
        ], [
            'pdf.required' => 'Pilih minimal satu PDF DPP.',
            'pdf.*.mimes' => 'Berkas DPP harus PDF.',
            'pdf.*.max' => 'Satu PDF paling besar 80 MB.',
            'buku.required' => 'Pilih satu pembukuan bulan lalu (.xlsx).',
            'buku.mimes' => 'Pembukuan harus berformat .xlsx. Berkas .xls: buka di Excel lalu Simpan Sebagai .xlsx.',
            'bulan.regex' => 'Tulis bulan seperti NOVEMBER 2026, atau kosongkan agar dibaca dari PDF.',
        ]);

        // Acuan pencocokan wajib ada (keputusan juru bayar, 16 Okt 2026): tanpa TNI aktif, proses ditolak.
        if (! Personel::aktif()->where('jenis', 'TNI')->exists()) {
            throw ValidationException::withMessages(['pdf' => 'Data personel masih kosong. Isi dulu lewat Data Personel, lalu proses lagi.']);
        }

        $pdf = [];
        foreach ($request->file('pdf') as $f) {
            $nama = $this->namaAman($f);
            if (in_array(strtolower($nama), array_map('strtolower', $pdf), true)) {
                throw ValidationException::withMessages(['pdf' => "Ada dua PDF bernama {$nama}. Ganti nama salah satunya."]);
            }
            $pdf[] = $nama;
        }
        $buku = $this->namaAman($request->file('buku'));

        $proses = ProsesGaji::create(['user_id' => $request->user()->id, 'bulan_paksa' => (string) $request->input('bulan'),
            'berkas' => ['pdf' => $pdf, 'buku' => $buku]]);
        Storage::disk('local')->deleteDirectory($proses->folder());      // sisa proses lama dengan id yang sama (mis. sesudah database di-reset)
        foreach ($request->file('pdf') as $i => $f) $f->storeAs($proses->folder('masuk'), $pdf[$i], 'local');
        $request->file('buku')->storeAs($proses->folder('masuk'), $buku, 'local');

        JalankanProsesGaji::dispatch($proses);

        return redirect()->route('gaji.show', $proses);
    }

    /**
     * Hapus satu proses: barisnya, seluruh berkas di storage (PDF/pembukuan yang diunggah dan hasil Python), dan catat di riwayat.
     * Proses yang masih berjalan tidak boleh dihapus, kecuali sudah macet melewati batas waktu.
     */
    public function hapus(Request $request, ProsesGaji $proses)
    {
        if (! $proses->bisaDihapus()) {
            return redirect()->route('gaji.index')->withErrors(['hapus' => 'Proses ini masih berjalan. Tunggu sampai selesai, lalu hapus.']);
        }

        $ringkas = "#{$proses->id} ".($proses->bulan ?: '(bulan tidak terbaca)').' · '.$proses->status;
        DB::transaction(function () use ($proses, $request, $ringkas) {
            Riwayat::create(['user_id' => $request->user()->id, 'operator' => $request->user()->name, 'aksi' => 'proses: hapus', 'personel_id' => null,
                'nrp' => '', 'nama' => '', 'perubahan' => ['Proses gaji' => [$ringkas, ''], 'Berkas' => [implode(', ', array_merge($proses->berkas['pdf'] ?? [], [$proses->berkas['buku'] ?? ''])), '']]]);
            $proses->delete();
        });
        Storage::disk('local')->deleteDirectory($proses->folder());
        DataGaji::lupakan();

        return redirect()->route('gaji.index')->with('ok', 'Proses '.($proses->bulan ?: '#'.$proses->id).' dihapus beserta berkas hasilnya.');
    }

    public function show(ProsesGaji $proses)
    {
        if ($proses->status === 'selesai') return redirect()->route('gaji.langkah', [$proses, 2]);

        return view('gaji.status', ['p' => $proses]);
    }

    /** Langkah 2-5: semuanya dibaca dari hasil.json yang ditulis Python. Langkah 1 adalah form unggah di index. */
    public function langkah(Request $request, ProsesGaji $proses, int $langkah)
    {
        abort_unless($langkah >= 2 && $langkah <= 5, 404);
        if ($proses->status !== 'selesai') return redirect()->route('gaji.show', $proses);
        $hasil = $proses->hasil() ?? abort(404);

        $data = ['p' => $proses, 'hasil' => $hasil, 'langkah' => $langkah, 'hal' => null, 'galat' => null, 'peringatan' => ''];
        $tinggi = $hasil['tingkat']['TINGGI'];

        if ($langkah === 2) {
            [$data['hal'], $data['galat']] = Halaman::bagi($hasil['personel'], 10, $request);
            $data['bermasalah'] = count(array_filter($hasil['personel'], fn ($o) => $o['masalah']));
            if ($data['bermasalah']) $data['peringatan'] = $data['bermasalah'].' anggota hitungannya tidak konsisten.';
        } elseif ($langkah === 3) {
            $data['lihat'] = $request->query('lihat') === 'temuan' ? 'temuan' : 'personel';
            if ($data['lihat'] === 'temuan') {
                $data['tingkat'] = in_array($request->query('tingkat'), ['TINGGI', 'SEDANG', 'INFO'], true) ? $request->query('tingkat') : '';
                $urut = array_flip(['TINGGI', 'SEDANG', 'INFO']);
                $daftar = array_values(array_filter($hasil['temuan'], fn ($t) => $data['tingkat'] === '' || $t['tingkat'] === $data['tingkat']));
                usort($daftar, fn ($a, $b) => ($urut[$a['tingkat']] ?? 9) <=> ($urut[$b['tingkat']] ?? 9));
                [$data['hal'], $data['galat']] = Halaman::bagi($daftar, 10, $request);
            } else {
                $baris = $hasil['pencocokan']['baris'] ?? [];
                $semua = array_keys($hasil['pencocokan']['hitung'] ?? []);
                $data['status'] = in_array($request->query('status'), array_merge($semua, ['semua']), true) ? $request->query('status') : 'perlu';
                $daftar = array_values(array_filter($baris, fn ($b) => match ($data['status']) {
                    'semua' => true,
                    'perlu' => ! in_array($b['status'], ['COCOK', 'BUKAN_BANK_INI'], true),
                    default => $b['status'] === $data['status'],
                }));
                [$data['hal'], $data['galat']] = Halaman::bagi($daftar, 10, $request);
            }
            if ($tinggi) $data['peringatan'] = $tinggi.' temuan tinggi perlu diperiksa sebelum transfer.';
        } elseif ($langkah === 4) {
            [$data['hal'], $data['galat']] = Halaman::bagi($hasil['perubahan'], 10, $request);
        } else {
            $data['berkas'] = $proses->berkasHasil();
        }

        return view('gaji.langkah', $data);
    }

    public function unduh(ProsesGaji $proses, string $nama)
    {
        abort_unless(array_key_exists($nama, $proses->berkasHasil()), 404);

        return response()->download($proses->path('hasil/'.$nama), $nama);
    }

    /** Nama berkas yang aman dipakai sebagai nama di disk dan dikirim ke Python. */
    private function namaAman(UploadedFile $f): string
    {
        $nama = preg_replace('/[^A-Za-z0-9._ -]+/', '_', basename($f->getClientOriginalName()));
        $nama = ltrim($nama, '. ');
        $ext = strtolower($f->getClientOriginalExtension());
        if (strlen($nama) > 120) $nama = substr($nama, 0, 115).'.'.$ext;

        return $nama !== '' ? $nama : 'berkas.'.$ext;
    }
}
