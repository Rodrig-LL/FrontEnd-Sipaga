<form method="post" action="{{ route('proses.aksi') }}" enctype="multipart/form-data" class="flex flex-col gap-3">
    @csrf
    <input type="hidden" name="do" value="upload">
    <section class="grid gap-3 md:grid-cols-2">
        @foreach ([['tni', 'TNI', 'gaji_tni_okt2026.xlsx', '8 Okt 2026, 09.12'], ['pns', 'PNS', 'gaji_pns_okt2026.xlsx', '8 Okt 2026, 09.20']] as [$kunci, $jenis, $contoh, $waktu])
            <div class="card">
                <h2>Softfile gaji {{ $jenis }}</h2>
                <div class="my-3 flex items-center gap-3 rounded-md border border-[#DAD8D0] p-3.5">
                    @include('partials.ikon', ['n' => 'file', 'u' => 28])
                    <div>
                        <b class="block font-mono text-[15px]">{{ $st['files'][$kunci] ?? $contoh }}</b>
                        <span class="muted">Sudah diunggah · {{ isset($st['files'][$kunci]) ? 'baru saja dipilih' : $waktu }}</span>
                    </div>
                </div>
                <label class="fl">Ganti berkas
                    <input type="file" name="{{ $kunci }}" accept=".xlsx,.xls,.csv" class="inp h-auto py-2 text-sm">
                </label>
            </div>
        @endforeach
    </section>
    <div class="flex flex-wrap items-center gap-3">
        <button type="submit" class="btn">Simpan berkas yang dipilih</button>
        <span class="muted">Prototipe: berkas hanya dicatat namanya, isinya belum dibaca.</span>
    </div>
</form>
<p class="muted">Format yang diterima: .xlsx atau .xls dari bagian keuangan. Kedua berkas harus untuk periode yang sama.</p>
