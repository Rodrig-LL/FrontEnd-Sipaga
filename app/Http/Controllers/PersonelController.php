<?php

namespace App\Http\Controllers;

use App\Http\Requests\SimpanPersonelRequest;
use App\Models\LogAktivitas;
use App\Models\Personel;
use App\Models\Riwayat;
use App\Services\PersonelService;
use App\Support\Halaman;
use App\Support\Pangkat;
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
            ->orderBy('id')
            ->get()->all();

        // Bawaan: jenis keanggotaan (TNI dulu), lalu nama. Pangkat paling masuk akal dilihat dari yang tertinggi.
        $urut = in_array($request->query('urut'), ['nama', 'pangkat', 'jenis'], true) ? $request->query('urut') : 'jenis';
        $arah = in_array($request->query('arah'), ['naik', 'turun'], true) ? $request->query('arah') : ($urut === 'pangkat' ? 'turun' : 'naik');
        $daftar = $this->urutkan($daftar, $urut, $arah);
        [$hal, $galat] = Halaman::bagi($daftar, 25, $request);

        $per = Personel::selectRaw('jenis, count(*) as n')->groupBy('jenis')->pluck('n', 'jenis');
        $jumlah = ['' => $per->sum(), 'TNI' => $per['TNI'] ?? 0, 'PNS' => $per['PNS'] ?? 0];
        $diubah = Personel::max('updated_at');

        return view('personel.index', compact('hal', 'galat', 'q', 'jenis', 'status', 'jumlah', 'diubah', 'urut', 'arah') + [
            'aktif' => Personel::aktif()->count(),
        ]);
    }

    /**
     * Urutkan menurut nama, pangkat/golongan, atau jenis keanggotaan. Urutan sama selalu jatuh ke nama, lalu id.
     * Pangkat TNI dan golongan PNS tidak sebanding, jadi keduanya tetap dikelompokkan per jenis (TNI dulu);
     * "naik" = terendah dulu, "turun" = tertinggi dulu. Pangkat kosong atau tak dikenal selalu di paling bawah kelompoknya.
     *
     * @param  list<Personel>  $daftar  sudah berurutan menurut id (usort stabil)
     */
    private function urutkan(array $daftar, string $urut, string $arah): array
    {
        $tanda = $arah === 'turun' ? -1 : 1;
        $nama = fn (Personel $a, Personel $b) => strcmp(mb_strtolower($a->nama), mb_strtolower($b->nama));
        $kelompok = fn (Personel $p) => $p->jenis === 'TNI' ? 0 : 1;

        usort($daftar, function (Personel $a, Personel $b) use ($urut, $tanda, $nama, $kelompok) {
            if ($urut === 'nama') return $tanda * $nama($a, $b);

            $j = $kelompok($a) <=> $kelompok($b);
            if ($urut === 'jenis') return $j !== 0 ? $tanda * $j : $nama($a, $b);

            if ($j !== 0) return $j;
            $ra = Pangkat::peringkat($a->jenis, $a->pangkat);
            $rb = Pangkat::peringkat($b->jenis, $b->pangkat);
            if ($ra === $rb) return $nama($a, $b);
            if ($ra === null) return 1;
            if ($rb === null) return -1;

            return $tanda * ($ra <=> $rb);
        });

        return $daftar;
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
        LogAktivitas::catat('personel_tambah', $p->nama.' ('.$p->jenis.')');

        return redirect()->route('personel.index')->with('ok', 'Data '.$p->nama.' sudah disimpan.');
    }

    public function update(SimpanPersonelRequest $request, Personel $personel)
    {
        [$p, $berubah] = $this->layanan->simpan($request->nilai($personel->tandai), $request->user(), $personel);
        if ($berubah) LogAktivitas::catat('personel_ubah', $p->nama.' ('.$p->jenis.')');

        return redirect()->route('personel.index')
            ->with('ok', $berubah ? 'Data '.$p->nama.' sudah disimpan.' : 'Tidak ada yang berubah pada data '.$p->nama.'.');
    }

    public function destroy(Request $request, Personel $personel)
    {
        $this->layanan->hapus($personel, $request->user());
        LogAktivitas::catat('personel_hapus', $personel->nama.' ('.$personel->jenis.')');

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
