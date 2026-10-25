<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\Personel;
use App\Models\Pinjaman;
use App\Services\PotonganService;
use App\Support\DataGaji;
use App\Support\Halaman;
use App\Support\PotonganData as P;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PotonganController extends Controller
{
    public function __construct(private PotonganService $layanan) {}

    /** Periode (YYYY-MM) yang dipakai untuk menampilkan keadaan pinjaman: periode proses terakhir, atau bulan ini. */
    private function periode(): string
    {
        $p = DataGaji::ada() ? ((DataGaji::hasil()['satuan']['periode'] ?? '') ?: '') : '';

        return P::periodeSah($p) ? $p : now()->format('Y-m');
    }

    public function index(Request $request)
    {
        $lihat = in_array($request->query('lihat'), ['pinjaman', 'aturan'], true) ? $request->query('lihat') : 'orang';
        $data = ['lihat' => $lihat, 'periode' => $this->periode(), 'satuan' => DataGaji::ada() ? (DataGaji::hasil()['satuan'] ?? null) : null,
            'proses' => DataGaji::proses(), 'boleh' => auth()->user()->can('kelola-potongan'), 'hal' => null, 'galat' => null];

        if ($lihat === 'orang') {
            $q = trim((string) $request->query('q', ''));
            $pot = DB::table('potongan')->selectRaw('personel_id, count(*) as n, sum(nominal) as total')->groupBy('personel_id')->get()->keyBy('personel_id');
            $pj = Pinjaman::where('status', 'aktif')->selectRaw('personel_id, count(*) as n')->groupBy('personel_id')->pluck('n', 'personel_id');
            $baris = Personel::aktif()->orderByDesc('jenis')->orderBy('nama')
                ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('nama', 'like', "%$q%")->orWhere('nrp', 'like', '%'.preg_replace('/\D/', '', $q).'%')))
                ->get()->map(fn (Personel $o) => ['p' => $o, 'n' => (int) ($pot[$o->id]->n ?? 0), 'total' => (int) ($pot[$o->id]->total ?? 0), 'pinjaman' => (int) ($pj[$o->id] ?? 0)])->all();
            [$data['hal'], $data['galat']] = Halaman::bagi($baris, 25, $request);
            $data['q'] = $q;
        } elseif ($lihat === 'pinjaman') {
            $daftar = Pinjaman::with('personel')->orderByRaw("case status when 'aktif' then 0 else 1 end")->orderBy('id')->get()
                ->map(fn (Pinjaman $x) => ['pj' => $x, 'keadaan' => P::jalanPada($x, $data['periode'])])->all();
            [$data['hal'], $data['galat']] = Halaman::bagi($daftar, 25, $request);
            $data['tanpaTenor'] = Pinjaman::where('status', 'aktif')->whereNull('tenor')->count();
        } else {
            $data['aturan'] = P::aturan();
        }

        return view('potongan.index', $data);
    }

    public function orang(Personel $personel)
    {
        $pokok = DB::table('pokok')->where('personel_id', $personel->id)->first();

        return view('potongan.orang', [
            'p' => $personel, 'nilai' => DB::table('potongan')->where('personel_id', $personel->id)->pluck('nominal', 'kode')->all(),
            'pinjaman' => $personel->pinjaman()->orderBy('id')->get(), 'pokok' => $pokok, 'periode' => $this->periode(),
            'aturan' => P::aturan(), 'boleh' => auth()->user()->can('kelola-potongan'),
            'edit' => request()->filled('pinjaman') ? Pinjaman::where('personel_id', $personel->id)->find((int) request()->query('pinjaman')) : null,
        ]);
    }

    public function simpanOrang(Request $request, Personel $personel)
    {
        $baru = $this->layanan->simpanOrang($personel, (array) $request->input('potongan', []), $request->user());

        if ($baru) LogAktivitas::catat('potongan', 'Potongan '.$personel->nama);

        return redirect()->route('potongan.orang', $personel)->with('ok', $baru ? 'Potongan '.$personel->nama.' disimpan.' : 'Tidak ada yang berubah.');
    }

    public function simpanPokok(Request $request, Personel $personel)
    {
        $baru = $this->layanan->simpanPokok($personel, $request->input('bulan'), $request->input('nominal'), $request->user());

        if ($baru) LogAktivitas::catat('potongan', 'Simpanan pokok '.$personel->nama);

        return redirect()->route('potongan.orang', $personel)->with('ok', $baru ? 'Simpanan pokok disimpan.' : 'Tidak ada yang berubah.');
    }

    public function tambahPinjaman(Request $request)
    {
        [$pj] = $this->layanan->simpanPinjaman($request->all(), $request->user());

        LogAktivitas::catat('potongan', 'Pinjaman baru '.$pj->personel?->nama);

        return redirect()->route('potongan.orang', $pj->personel_id)->with('ok', 'Pinjaman dicatat.');
    }

    public function ubahPinjaman(Request $request, Pinjaman $pinjaman)
    {
        [, $berubah] = $this->layanan->simpanPinjaman($request->all(), $request->user(), $pinjaman);

        if ($berubah) LogAktivitas::catat('potongan', 'Pinjaman diubah '.$pinjaman->personel?->nama);

        return redirect()->route('potongan.orang', $pinjaman->personel_id)->with('ok', $berubah ? 'Pinjaman disimpan.' : 'Tidak ada yang berubah.');
    }

    public function simpanAturan(Request $request)
    {
        $berubah = $this->layanan->simpanAturan($request->all(), $request->user());

        if ($berubah) LogAktivitas::catat('potongan', 'Aturan potongan');

        return redirect()->route('potongan.index', ['lihat' => 'aturan'])->with('ok', $berubah ? 'Aturan disimpan.' : 'Tidak ada yang berubah.');
    }
}
