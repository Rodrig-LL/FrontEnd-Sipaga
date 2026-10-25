<?php

namespace App\Http\Requests;

use App\Models\Personel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SimpanPersonelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;   // pembatasan peran ada di route (middleware can:)
    }

    protected function prepareForValidation(): void
    {
        $status = strtolower(trim((string) $this->input('status'))) ?: 'aktif';
        $nrpAsli = trim((string) $this->input('nrp_asli'));
        $this->merge([
            'jenis' => strtoupper(trim((string) $this->input('jenis'))),
            'nrp_asli' => $nrpAsli,
            'bank' => strtoupper(trim((string) $this->input('bank'))),
            'rekening' => preg_replace('/[\s.\-]/', '', (string) $this->input('rekening')),
            'status' => $status,
            'tgl_keluar' => $status === 'aktif' ? '' : trim((string) $this->input('tgl_keluar')),
            'alasan_keluar' => $status === 'aktif' ? '' : trim((string) $this->input('alasan_keluar')),
        ]);
    }

    public function rules(): array
    {
        $id = $this->route('personel')?->id;
        $noun = $this->input('jenis') === 'PNS' ? 'NIP' : 'NRP';

        return [
            'jenis' => ['required', Rule::in(Personel::JENIS)],
            'nama' => ['required', 'string', 'max:100'],
            'nrp_asli' => ['required', 'string', 'max:30', function ($attr, $v, $fail) use ($id, $noun) {
                $digit = preg_replace('/\D/', '', $v);
                if (strlen($digit) < 5) return $fail("$noun harus berisi minimal 5 angka.");
                $ada = Personel::where('nrp', $digit)->when($id, fn ($q) => $q->where('id', '!=', $id))->first();
                if ($ada) $fail('Nomor ini sudah dipakai oleh '.$ada->nama.'.');
            }],
            'pangkat' => ['nullable', 'string', 'max:60'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'ket' => ['nullable', 'string', 'max:60'],
            'seksi' => ['nullable', 'string', 'max:100'],
            'tgl_lahir' => ['nullable', 'date_format:Y-m-d'],
            'bank' => ['nullable', Rule::in(Personel::BANK)],
            'rekening' => ['nullable', 'regex:/^\d+$/', 'max:30'],
            'nama_rekening' => ['nullable', 'string', 'max:100'],
            'status' => ['required', Rule::in(['aktif', 'keluar'])],
            'tgl_keluar' => ['required_if:status,keluar', 'nullable', 'date_format:Y-m-d'],
            'alasan_keluar' => ['required_if:status,keluar', 'nullable', 'string', 'max:200'],
            'hapus_tanda' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'jenis.in' => 'Pilih TNI atau PNS.', 'jenis.required' => 'Pilih TNI atau PNS.',
            'nama.required' => 'Nama harus diisi.',
            'nrp_asli.required' => 'NRP / NIP harus diisi.',
            'bank.in' => 'Pilih BRI, BSI, BTN, atau kosongkan.',
            'rekening.regex' => 'Nomor rekening hanya boleh berisi angka.',
            'tgl_lahir.date_format' => 'Tanggal lahir tidak sah.',
            'tgl_keluar.required_if' => 'Tanggal keluar harus diisi.',
            'tgl_keluar.date_format' => 'Tanggal keluar tidak sah.',
            'alasan_keluar.required_if' => 'Alasan keluar harus diisi (mis. pindah satuan, pensiun).',
        ];
    }

    /**
     * Nilai siap simpan. $tandaLama = tanda yang sudah ada di personel (tidak diubah form kecuali dihapus).
     */
    public function nilai(string $tandaLama = ''): array
    {
        $d = $this->validated();
        $str = fn ($k) => trim((string) ($d[$k] ?? ''));

        return [
            'jenis' => $d['jenis'], 'nama' => $str('nama'), 'pangkat' => $str('pangkat'),
            'nrp' => preg_replace('/\D/', '', $d['nrp_asli']), 'nrp_asli' => $d['nrp_asli'],
            'jabatan' => $str('jabatan'), 'ket' => $str('ket'), 'seksi' => $str('seksi'),
            'tgl_lahir' => $str('tgl_lahir'), 'bank' => $str('bank'), 'rekening' => $str('rekening'),
            'nama_rekening' => $str('nama_rekening'), 'status' => $d['status'],
            'tgl_keluar' => $str('tgl_keluar'), 'alasan_keluar' => $str('alasan_keluar'),
            'tandai' => $this->boolean('hapus_tanda') ? '' : $tandaLama,
        ];
    }
}
