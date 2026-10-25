<?php

namespace App\Support;

/**
 * Peringkat pangkat TNI dan golongan PNS untuk pengurutan. Pangkat disimpan sebagai teks bebas
 * ("Kapten Caj (K)", "Penata Muda Tk. I III/b"), jadi peringkat dibaca dari kata pertamanya (TNI)
 * atau dari penulisan golongan romawi/huruf (PNS). Angka lebih besar = lebih tinggi.
 */
class Pangkat
{
    /** Dari terendah ke tertinggi (TNI AD): tamtama, bintara, perwira pertama, menengah, tinggi. */
    private const TNI = ['prada', 'pratu', 'praka', 'kopda', 'koptu', 'kopka', 'serda', 'sertu', 'serka', 'serma', 'pelda', 'peltu',
        'letda', 'lettu', 'kapten', 'mayor', 'letkol', 'kolonel', 'brigjen', 'mayjen', 'letjen', 'jenderal'];

    private const ROMAWI = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4];

    /** @return int|null null bila pangkat kosong atau tidak dikenal */
    public static function peringkat(string $jenis, string $pangkat): ?int
    {
        $t = mb_strtolower(trim($pangkat));
        if ($t === '') return null;

        if ($jenis === 'PNS') {
            if (! preg_match('~\b(iv|iii|ii|i)\s*/\s*([a-e])\b~', $t, $m)) return null;

            return self::ROMAWI[$m[1]] * 10 + (ord($m[2]) - ord('a') + 1);
        }

        $i = array_search(preg_split('/[\s(]+/', $t)[0], self::TNI, true);

        return $i === false ? null : $i + 1;
    }
}
