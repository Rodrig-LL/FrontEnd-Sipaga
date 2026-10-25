<?php

namespace App\Http\Controllers;

use App\Models\ImporPersonel;
use App\Services\ImporGagal;
use App\Services\ImporPersonelService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ImporPersonelController extends Controller
{
    public function __construct(private ImporPersonelService $layanan) {}

    public function index()
    {
        return view('personel.impor.index', [
            'riwayat' => ImporPersonel::with('user')->latest('id')->limit(10)->get(),
            'bolehImpor' => Gate::allows('impor-personel'),
            'pythonAda' => is_file(config('sipaga.python')),
        ]);
    }

    /** Baca berkas (Python) dan buat pratinjau. Database belum berubah. */
    public function store(Request $request, string $jenis)
    {
        Gate::authorize('impor-personel');
        abort_unless(array_key_exists($jenis, ImporPersonel::JENIS), 404);

        $request->validate([
            'berkas' => ['required', 'array', 'min:1', 'max:'.($jenis === 'nominatif' ? 2 : 6)],
            'berkas.*' => ['file', 'mimes:xlsx', 'max:20480'],
        ], [
            'berkas.required' => 'Pilih berkas Excel (.xlsx) terlebih dahulu.',
            'berkas.max' => $jenis === 'nominatif' ? 'Paling banyak dua nominatif: satu militer dan satu PNS.' : 'Paling banyak enam pembukuan sekaligus.',
            'berkas.*.mimes' => 'Berkas harus berformat .xlsx. Berkas .xls: buka di Excel, lalu Simpan Sebagai .xlsx.',
            'berkas.*.max' => 'Satu berkas paling besar 20 MB.',
        ]);

        try {
            $impor = $this->layanan->pratinjau($jenis, $request->file('berkas'), $request->user());
        } catch (ImporGagal $e) {
            throw ValidationException::withMessages(['berkas' => $e->getMessage()]);
        }

        return redirect()->route('personel.impor.show', $impor);
    }

    public function show(ImporPersonel $impor)
    {
        return view('personel.impor.show', ['i' => $impor, 'r' => $impor->rencana, 'bolehImpor' => Gate::allows('impor-personel'),
            'basi' => $impor->status === 'pratinjau' && $impor->sidik !== $this->layanan->sidik()]);
    }

    public function terapkan(Request $request, ImporPersonel $impor)
    {
        Gate::authorize('impor-personel');
        try {
            $n = $this->layanan->terapkan($impor, $request->user());
        } catch (ImporGagal $e) {
            return redirect()->route('personel.impor.show', $impor)->withErrors(['impor' => $e->getMessage()]);
        }

        $bagian = array_filter(['ditambah' => $n['tambah'], 'diperbarui' => $n['ubah'], 'ditandai' => $n['tandai'], 'pinjaman dicatat' => $n['pinjaman'], 'berhenti' => $n['berhenti']]);

        return redirect()->route($impor->jenis === 'potongan' || $impor->jenis === 'arisan' ? 'potongan.index' : 'personel.index')
            ->with('ok', 'Impor diterapkan: '.(implode(', ', array_map(fn ($v, $k) => "$v $k", $bagian, array_keys($bagian))) ?: 'tidak ada perubahan').'.');
    }
}
