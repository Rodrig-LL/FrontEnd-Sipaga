<?php

namespace App\Services;

use App\Models\Personel;
use App\Models\Pinjaman;
use App\Models\Riwayat;
use App\Models\User;
use App\Support\PotonganData as P;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Simpan potongan satuan dengan riwayat; padanan simpan_orang/simpan_pinjaman/simpan_pokok/simpan_aturan di potongan.py. */
class PotonganService
{
    /** Isian rupiah/angka dari form: '5.000', 'Rp 5.000', 5000 -> 5000. Kosong -> null. */
    public static function angka(mixed $v, string $nama, array &$galat, bool $bulat = true, bool $bolehKosong = true): int|float|null
    {
        if ($v === null || (is_string($v) && trim($v) === '')) {
            if (! $bolehKosong) $galat[$nama] = 'Harus diisi.';

            return null;
        }
        if (is_bool($v)) {
            $galat[$nama] = 'Harus berupa angka.';

            return null;
        }
        if (is_int($v) || is_float($v)) {
            $x = $v;
        } else {
            $s = str_replace(' ', '', trim(preg_replace('/^\s*rp\.?\s*/i', '', (string) $v)));
            $s = $bulat ? str_replace(['.', ','], ['', '.'], $s) : str_replace(',', '.', $s);
            if (! is_numeric($s)) {
                $galat[$nama] = 'Harus berupa angka.';

                return null;
            }
            $x = (float) $s;
        }
        if ($x < 0) {
            $galat[$nama] = 'Tidak boleh negatif.';

            return null;
        }
        if ($bulat) {
            if ($x != floor($x)) {
                $galat[$nama] = 'Harus bilangan bulat.';

                return null;
            }

            return (int) $x;
        }

        return round($x, 2);
    }

    public static function rp(int|float|null $n): string
    {
        return $n ? number_format((int) $n, 0, ',', '.') : '';
    }

    /** Ubah potongan tetap satu orang. Kosong/0 = tidak dipotong. Dicatat per jenis di riwayat. */
    public function simpanOrang(Personel $p, array $nilai, User $oleh): bool
    {
        $g = $baru = [];
        $boleh = P::kodePotongan();
        foreach ($boleh as $k) if (array_key_exists($k, $nilai)) $baru[$k] = self::angka($nilai[$k], $k, $g) ?? 0;
        if ($asing = array_diff(array_keys($nilai), $boleh)) $g['_'] = 'Jenis potongan tidak dikenal: '.implode(', ', $asing);
        if ($g) throw ValidationException::withMessages($g);

        return DB::transaction(function () use ($p, $baru, $oleh) {
            $lama = DB::table('potongan')->where('personel_id', $p->id)->pluck('nominal', 'kode')->all();
            $beda = [];
            foreach ($baru as $k => $v) if ((int) ($lama[$k] ?? 0) !== $v) $beda[P::label($k)] = [self::rp($lama[$k] ?? 0), self::rp($v)];
            if (! $beda) return false;
            foreach ($baru as $k => $v) self::setPot($p->id, $k, $v);
            $this->catat('potongan: ubah', $oleh, $p, $beda);

            return true;
        });
    }

    public static function setPot(int $pid, string $kode, int $nominal): void
    {
        if ($nominal > 0) {
            DB::table('potongan')->updateOrInsert(['personel_id' => $pid, 'kode' => $kode], ['nominal' => $nominal]);
        } else {
            DB::table('potongan')->where('personel_id', $pid)->where('kode', $kode)->delete();
        }
    }

    private const PJ_LABEL = ['pemberi' => 'Pemberi', 'cicilan' => 'Cicilan/bulan', 'mulai' => 'Mulai dipotong', 'tenor' => 'Tenor',
        'tgl_cair' => 'Tanggal cair', 'catatan' => 'Catatan', 'status' => 'Status'];

    private static function pjTeks(string $k, mixed $v): string
    {
        if ($v === null || $v === '') return $k === 'tenor' ? '(belum diisi)' : '';

        return match ($k) {
            'pemberi' => P::label($v), 'cicilan' => self::rp($v), 'mulai' => mb_strtolower(P::teksPeriode($v)), 'tenor' => $v.' bulan', default => (string) $v,
        };
    }

    /** Tambah (tanpa $pj) atau ubah satu pinjaman koperasi/LURJA. @return array{0: Pinjaman, 1: bool} */
    public function simpanPinjaman(array $d, User $oleh, ?Pinjaman $pj = null): array
    {
        $g = [];
        $pemberi = (string) ($d['pemberi'] ?? '');
        if (! array_key_exists($pemberi, P::PINJAMAN)) $g['pemberi'] = 'Pilih Koperasi Ditajenad atau LURJA.';
        $cicilan = self::angka($d['cicilan'] ?? null, 'cicilan', $g, true, false);
        if ($cicilan === 0) $g['cicilan'] = 'Cicilan harus lebih dari 0.';
        $mulai = substr(trim((string) ($d['mulai'] ?? '')), 0, 7);
        if (! P::periodeSah($mulai)) $g['mulai'] = 'Pilih bulan potongan pertama.';
        $tenor = self::angka($d['tenor'] ?? null, 'tenor', $g);
        if ($tenor === 0) $g['tenor'] = 'Minimal 1 bulan, atau kosongkan bila belum diketahui.';
        $tgl = trim((string) ($d['tgl_cair'] ?? ''));
        if ($tgl !== '' && ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $tgl)) $g['tgl_cair'] = 'Tanggal tidak sah.';
        $status = (string) ($d['status'] ?? 'aktif');
        if (! in_array($status, ['aktif', 'lunas', 'batal'], true)) $g['status'] = 'Status harus aktif, lunas, atau batal.';
        $p = $pj ? $pj->personel : Personel::find((int) ($d['personel_id'] ?? 0));
        if (! $p) $g['_'] = 'Personel ini sudah tidak ada.';
        if ($g) throw ValidationException::withMessages($g);

        $v = ['pemberi' => $pemberi, 'cicilan' => $cicilan, 'mulai' => $mulai, 'tenor' => $tenor, 'tgl_cair' => $tgl,
            'catatan' => mb_substr(trim((string) ($d['catatan'] ?? '')), 0, 500), 'status' => $status];

        return DB::transaction(function () use ($v, $p, $pj, $oleh, $status) {
            if (! $pj) {
                $pj = Pinjaman::create($v + ['personel_id' => $p->id]);
                $beda = [];
                foreach ($v as $k => $x) if ($x !== null && $x !== '' && $k !== 'status') $beda[self::PJ_LABEL[$k]] = ['', self::pjTeks($k, $x)];
                $this->catat('pinjaman: tambah', $oleh, $p, $beda);

                return [$pj, true];
            }
            $lama = $pj->only(array_keys($v));
            $beda = [];
            foreach ($v as $k => $x) if ($lama[$k] !== $x) $beda[self::PJ_LABEL[$k]] = [self::pjTeks($k, $lama[$k]), self::pjTeks($k, $x)];
            if (! $beda) return [$pj, false];
            $aksi = $lama['status'] !== $status ? "pinjaman: $status" : 'pinjaman: ubah';
            $pj->update($v);
            $this->catat($aksi, $oleh, $p, $beda);

            return [$pj, true];
        });
    }

    /** Simpanan pokok: dipotong sekali di $bulan. Bulan kosong = dibatalkan. */
    public function simpanPokok(Personel $p, ?string $bulan, mixed $nominal, User $oleh): bool
    {
        $g = [];
        $bulan = substr(trim((string) $bulan), 0, 7);
        if ($bulan !== '' && ! P::periodeSah($bulan)) $g['bulan'] = 'Pilih bulan.';
        $n = self::angka($nominal, 'nominal', $g);
        if ($bulan !== '' && ! $n) {
            $n = P::aturan()['pokok'];
            if (! $n) $g['nominal'] = 'Isi nominal simpanan pokok (atau atur nominal bawaannya di Potongan > Aturan).';
        }
        if ($g) throw ValidationException::withMessages($g);

        return DB::transaction(function () use ($p, $bulan, $n, $oleh) {
            $lama = DB::table('pokok')->where('personel_id', $p->id)->first();
            $teks = fn ($b, $x) => $b === '' || $b === null ? '' : self::rp($x).' ('.mb_strtolower(P::teksPeriode($b)).')';
            $a = $lama ? $teks($lama->bulan, $lama->nominal) : '';
            $b = $bulan !== '' ? $teks($bulan, $n) : '';
            if ($a === $b) return false;
            if ($bulan !== '') DB::table('pokok')->updateOrInsert(['personel_id' => $p->id], ['bulan' => $bulan, 'nominal' => $n]);
            else DB::table('pokok')->where('personel_id', $p->id)->delete();
            $this->catat('potongan: simpanan pokok', $oleh, $p, ['Simpanan pokok' => [$a, $b]]);

            return true;
        });
    }

    /** Sisa YBS, nominal simpanan pokok, dan batas pinjam per pemberi. */
    public function simpanAturan(array $d, User $oleh): bool
    {
        $g = $baru = [];
        $baru['sisa_ybs'] = self::angka($d['sisa_ybs'] ?? null, 'sisa_ybs', $g, true, false);
        $baru['pokok'] = self::angka($d['pokok'] ?? null, 'pokok', $g) ?: null;
        foreach (P::PEMBERI_BATAS as $pb) {
            $b = $d['batas'][$pb] ?? [];
            $persen = self::angka($b['persen'] ?? null, "batas.$pb.persen", $g, false);
            if ($persen !== null && ! ($persen > 0 && $persen <= 100)) $g["batas.$pb.persen"] = 'Antara 0 dan 100.';
            $tenor = self::angka($b['tenor_maks'] ?? null, "batas.$pb.tenor_maks", $g);
            if ($tenor === 0) $g["batas.$pb.tenor_maks"] = 'Minimal 1 bulan, atau kosongkan.';
            $baru['batas'][$pb] = ['persen' => $persen, 'sisa_min' => self::angka($b['sisa_min'] ?? null, "batas.$pb.sisa_min", $g), 'tenor_maks' => $tenor];
        }
        if ($g) throw ValidationException::withMessages($g);

        return DB::transaction(function () use ($baru, $oleh) {
            $lama = P::aturan();
            $beda = [];
            if ($lama['sisa_ybs'] !== $baru['sisa_ybs']) $beda['Sisa gaji minimal (YBS)'] = [self::rp($lama['sisa_ybs']) ?: '0', self::rp($baru['sisa_ybs']) ?: '0'];
            if ($lama['pokok'] !== $baru['pokok']) $beda['Simpanan pokok'] = [self::rp($lama['pokok']), self::rp($baru['pokok'])];
            foreach (P::PEMBERI_BATAS as $pb) {
                foreach (['persen' => 'maks % gaji', 'sisa_min' => 'sisa gaji minimal', 'tenor_maks' => 'tenor maks'] as $k => $lbl) {
                    $a = $lama['batas'][$pb][$k];
                    $b = $baru['batas'][$pb][$k];
                    if ($a != $b) {
                        $f = fn ($x) => $x === null ? '' : ($k === 'persen' ? rtrim(rtrim(number_format($x, 2, '.', ''), '0'), '.').'%' : ($k === 'sisa_min' ? self::rp($x) : $x.' bln'));
                        $beda['Batas '.P::label($pb).': '.$lbl] = [$f($a), $f($b)];
                    }
                }
            }
            if (! $beda) return false;
            foreach (['sisa_ybs', 'pokok', 'batas'] as $k) DB::table('aturan_potongan')->updateOrInsert(['kunci' => $k], ['nilai' => json_encode($baru[$k])]);
            $this->catat('aturan potongan', $oleh, null, $beda);

            return true;
        });
    }

    public function catat(string $aksi, User $oleh, ?Personel $p, array $perubahan): void
    {
        Riwayat::create(['user_id' => $oleh->id, 'operator' => $oleh->name, 'aksi' => $aksi, 'personel_id' => $p?->id,
            'nrp' => $p?->nrp ?? '', 'nama' => $p?->nama ?? '', 'perubahan' => $perubahan]);
    }
}
