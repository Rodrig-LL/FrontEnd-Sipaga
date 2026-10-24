<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimpanPersonelRequest;
use App\Models\Personel;
use App\Models\Riwayat;
use App\Services\PersonelService;
use App\Support\Halaman;
use Illuminate\Http\Request;

class PersonelController extends Controller
{
    public function __construct(private PersonelService $layanan) {}

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q', ''));
        $jenis = in_array($request->query('jenis'), Personel::JENIS, true) ? $request->query('jenis') : '';
        $status = (string) $request->query('status', '');

        $daftar = Personel::query()
            ->when($jenis !== '', fn ($w) => $w->where('jenis', $jenis))
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nama', 'like', '%'.$q.'%')
                ->orWhere('nrp', 'like', '%'.preg_replace('/\D/', '', $q).'%')->orWhere('nrp_asli', 'like', '%'.$q.'%')))
            ->when($status === 'aktif', fn ($w) => $w->aktif())
            ->when($status === 'keluar', fn ($w) => $w->where('status', 'keluar'))
            ->when($status === 'tandai', fn ($w) => $w->where('tandai', '!=', ''))
            ->when($status === 'tanpa_bank', fn ($w) => $w->aktif()->where('bank', ''))
            ->orderByDesc('jenis')->orderBy('nama')->orderBy('id')
            ->get()->all();
        [$hal, $galat] = Halaman::bagi($daftar, 25, $request);

        $per = Personel::selectRaw('jenis, count(*) as n')->groupBy('jenis')->pluck('n', 'jenis');
        $jumlah = ['' => $per->sum(), 'TNI' => $per['TNI'] ?? 0, 'PNS' => $per['PNS'] ?? 0];
        $diubah = Personel::max('updated_at');

        return view('personel.index', compact('hal', 'galat', 'q', 'jenis', 'status', 'jumlah', 'diubah') + [
            'aktif' => Personel::aktif()->count(),
        ]);
    }

    public function create()
    {
        return view('personel.form', ['r' => null, 'nilai' => $this->nilaiAwal(null)]);
    }

    public function edit(Personel $personel)
    {
        return view('personel.form', ['r' => $personel, 'nilai' => $this->nilaiAwal($personel),
            'riwayat' => Riwayat::where('personel_id', $personel->id)->latest('id')->limit(20)->get()]);
    }

    public function store(SimpanPersonelRequest $request)
    {
        [$p] = $this->layanan->simpan($request->nilai(), $request->user());

        return redirect()->route('personel.index')->with('ok', 'Data '.$p->nama.' sudah disimpan.');
    }

    public function update(SimpanPersonelRequest $request, Personel $personel)
    {
        [$p, $berubah] = $this->layanan->simpan($request->nilai($personel->tandai), $request->user(), $personel);

        return redirect()->route('personel.index')
            ->with('ok', $berubah ? 'Data '.$p->nama.' sudah disimpan.' : 'Tidak ada yang berubah pada data '.$p->nama.'.');
    }

    public function destroy(Request $request, Personel $personel)
    {
        $this->layanan->hapus($personel, $request->user());

        return redirect()->route('personel.index')->with('ok', 'Data '.$personel->nama.' dihapus. Isi terakhirnya tersimpan di riwayat.');
    }

    public function riwayat(Request $request)
    {
        $personel = $request->filled('personel') ? Personel::find((int) $request->query('personel')) : null;
        $daftar = Riwayat::query()
            ->when($request->filled('personel'), fn ($w) => $w->where('personel_id', (int) $request->query('personel')))
            ->latest('id')->limit(500)->get()->all();
        [$hal, $galat] = Halaman::bagi($daftar, 25, $request);

        return view('personel.riwayat', compact('hal', 'galat', 'personel'));
    }

    private function nilaiAwal(?Personel $p): array
    {
        $awal = ['jenis' => 'TNI', 'nama' => '', 'nrp_asli' => '', 'pangkat' => '', 'jabatan' => '', 'ket' => '', 'seksi' => '',
            'tgl_lahir' => '', 'bank' => '', 'rekening' => '', 'nama_rekening' => '', 'status' => 'aktif', 'tgl_keluar' => '', 'alasan_keluar' => ''];

        return $p ? array_intersect_key($p->nilaiTeks(), $awal) : $awal;
    }
}
