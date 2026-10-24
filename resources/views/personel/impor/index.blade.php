@extends('layouts.app')
@section('title', 'Impor personel')

@section('konten')
@include('partials.kembali', ['href' => route('personel.index'), 'label' => 'Kembali ke Data Personel'])

<header>
    <p class="text-[13px] text-muted">Data Personel</p>
    <h1 class="mt-1 text-[26px] font-semibold tracking-tight">Impor dari Excel</h1>
    <p class="mt-1.5 text-[15px] text-body">Berkas dibaca dulu dan hasilnya ditampilkan sebagai pratinjau. Database baru berubah setelah Anda menekan <b>Terapkan</b>.</p>
</header>

@if ($errors->has('berkas') || $errors->has('berkas.*'))
    <p class="note" role="alert"><span class="alert-t">@include('partials.ikon', ['n' => 'err', 'u' => 16])</span> {{ $errors->first('berkas') ?: $errors->first('berkas.*') }}</p>
@endif

@unless ($pythonAda)
    <p class="note" role="alert"><b>Python belum terpasang untuk aplikasi ini.</b> Jalankan:
        <code>python3 -m venv python/.venv &amp;&amp; python/.venv/bin/pip install -r python/requirements.txt</code></p>
@endunless

@if ($bolehImpor)
    <section class="card max-w-[820px]">
        <h2>1. Nominatif</h2>
        <p class="muted my-1.5 leading-normal">Daftar personel satuan: satu berkas militer (kolom NAMA, NRP) dan/atau satu PNS (NAMA, NIP). NRP/NIP baru ditambahkan, yang sudah ada diperbarui (nama, pangkat, jabatan, KET, seksi, tanggal lahir). Bank dan rekening tidak disentuh. Yang tidak tercantum lagi hanya <b>ditandai</b>, tidak otomatis keluar.</p>
        <form method="post" action="{{ route('personel.impor.store', 'nominatif') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2.5">
            @csrf
            <label class="fl min-w-[260px] flex-1">Berkas nominatif (.xlsx, maksimal dua)
                <input type="file" name="berkas[]" multiple accept=".xlsx" class="inp h-auto py-2 text-sm {{ $errors->has('berkas') && old('_jenis') !== 'bank' ? 'bad' : '' }}">
            </label>
            <button type="submit" class="btn btn-p h-11" @disabled(! $pythonAda)>Periksa berkas</button>
        </form>
    </section>

    <section class="card max-w-[820px]">
        <h2>2. Bank dan rekening dari pembukuan</h2>
        <p class="muted my-1.5 leading-normal">Pembukuan (BUKU TNI dan BUKU PNS) tiap bank. Banknya dibaca dari <b>nama berkas</b>, jadi harus menyebut BRI, BSI, atau BTN. Nomor rekening dan nama rekening yang kosong di buku tidak menghapus yang sudah ada.</p>
        <form method="post" action="{{ route('personel.impor.store', 'bank') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2.5">
            @csrf
            <input type="hidden" name="_jenis" value="bank">
            <label class="fl min-w-[260px] flex-1">Berkas pembukuan (.xlsx)
                <input type="file" name="berkas[]" multiple accept=".xlsx" class="inp h-auto py-2 text-sm">
            </label>
            <button type="submit" class="btn btn-p h-11" @disabled(! $pythonAda)>Periksa berkas</button>
        </form>
    </section>
    <section class="card max-w-[820px]">
        <h2>3. Potongan satuan dari pembukuan bulan lalu</h2>
        <p class="muted my-1.5 leading-normal">Potongan tetap dan cicilan bank tiap orang (BUKU TNI dan BUKU PNS), serta pinjaman Koperasi/LURJA yang sedang berjalan (dicatat dengan tenor kosong, diisi menyusul). Dibaca dari judul kolom di buku itu sendiri. Cukup sekali, sebagai isi awal.</p>
        <form method="post" action="{{ route('personel.impor.store', 'potongan') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2.5">
            @csrf
            <label class="fl min-w-[260px] flex-1">Pembukuan bulan lalu (.xlsx)
                <input type="file" name="berkas[]" multiple accept=".xlsx" class="inp h-auto py-2 text-sm">
            </label>
            <button type="submit" class="btn btn-p h-11" @disabled(! $pythonAda)>Periksa berkas</button>
        </form>
    </section>

    <section class="card max-w-[820px]">
        <h2>4. Daftar arisan Persit bulan ini</h2>
        <p class="muted my-1.5 leading-normal">Tiap bulan. Banknya dibaca dari judul daftar ("… GAJI DI BRI") atau nama berkas. Yang tidak tercantum lagi <b>di bank yang sama</b> berhenti arisan.</p>
        <form method="post" action="{{ route('personel.impor.store', 'arisan') }}" enctype="multipart/form-data" class="mt-3 flex flex-wrap items-end gap-2.5">
            @csrf
            <label class="fl min-w-[260px] flex-1">DAFTAR ARISAN PERSIT (.xlsx)
                <input type="file" name="berkas[]" multiple accept=".xlsx" class="inp h-auto py-2 text-sm">
            </label>
            <button type="submit" class="btn btn-p h-11" @disabled(! $pythonAda)>Periksa berkas</button>
        </form>
    </section>
@else
    <p class="note">Hanya Admin dan Juru Bayar yang bisa mengimpor. Anda tetap bisa melihat pratinjau yang sudah dibuat.</p>
@endif

<section class="card overflow-hidden p-0">
    <h2 class="px-3.5 pt-3.5">Impor sebelumnya</h2>
    <div class="overflow-x-auto">
        <table class="t min-w-[640px]">
            <thead><tr><th scope="col" class="pl-3.5">Waktu</th><th scope="col">Jenis</th><th scope="col">Berkas</th><th scope="col">Oleh</th><th scope="col">Status</th><th scope="col" class="r pr-3.5">Aksi</th></tr></thead>
            <tbody>
                @forelse ($riwayat as $d)
                    <tr>
                        <td class="whitespace-nowrap pl-3.5">{{ $d->created_at->translatedFormat('j M Y H:i') }}</td>
                        <td>{{ \App\Models\ImporPersonel::JENIS[$d->jenis] }}</td>
                        <td>{{ implode(', ', $d->berkas) }}</td>
                        <td>{{ $d->user?->name ?? '—' }}</td>
                        <td>{{ $d->status === 'diterapkan' ? 'Diterapkan' : 'Pratinjau' }}</td>
                        <td class="r act pr-3.5"><a class="lnk" href="{{ route('personel.impor.show', $d) }}">Buka</a></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="p-6 text-center muted">Belum ada impor.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
@endsection
