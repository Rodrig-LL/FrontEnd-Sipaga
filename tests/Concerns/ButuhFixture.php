<?php

namespace Tests\Concerns;

/**
 * Berkas uji (DPP, nominatif, pembukuan) tidak disimpan di repo karena berisi data karangan.
 * Tes yang membutuhkannya dilompati bila berkasnya tidak ada; taruh berkas sendiri di tests/fixtures/ untuk menjalankannya.
 */
trait ButuhFixture
{
    protected function fixture(string $path): string
    {
        $lengkap = base_path('tests/fixtures/'.$path);
        if (! is_file($lengkap)) $this->markTestSkipped("Berkas uji tidak ada: tests/fixtures/$path");

        return $lengkap;
    }
}
