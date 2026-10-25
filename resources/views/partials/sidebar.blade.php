@php
    $nama = auth()->user()->name;
    $kata = preg_split('/\s+/', trim($nama));
    $inisial = $nama[0] === '[' ? 'JB' : strtoupper(substr($kata[0], 0, 1).(isset($kata[1]) ? substr($kata[1], 0, 1) : ''));
@endphp
<nav aria-label="Navigasi utama" class="sticky top-0 flex min-h-screen flex-col gap-[22px] px-3 py-5">
    <div class="flex items-center gap-2.5 px-2">
        @include('partials.logo', ['ukuran' => 'h-10 w-12'])
        <div>
            <b class="block text-base tracking-[.06em]">SIPAGA</b>
            <span class="block text-xs text-[#A9B2A3]">Slip Gaji Terpadu</span>
        </div>
    </div>

    <div class="flex flex-col gap-1">
        <a href="{{ route('dasbor') }}" class="nav-a {{ request()->routeIs('dasbor') ? 'on' : '' }}" @if (request()->routeIs('dasbor')) aria-current="page" @endif>@include('partials.ikon', ['n' => 'home'])Dasbor</a>
    </div>

    <div class="flex flex-col gap-1">
        <div class="px-2.5 pb-1 text-[11px] font-semibold tracking-[.1em] text-[#A9B2A3]">PROSES GAJI</div>
        <a href="{{ route('gaji.index') }}" class="nav-a {{ request()->routeIs('gaji.*') ? 'on' : '' }}" @if (request()->routeIs('gaji.*')) aria-current="page" @endif>@include('partials.ikon', ['n' => 'up'])Proses Gaji</a>
        <a href="{{ route('slip.index') }}" class="nav-a {{ request()->routeIs('slip.*') ? 'on' : '' }}" @if (request()->routeIs('slip.*')) aria-current="page" @endif>@include('partials.ikon', ['n' => 'doc'])Slip Gaji</a>
    </div>

    <div class="flex flex-col gap-1">
        <div class="px-2.5 pb-1 text-[11px] font-semibold tracking-[.1em] text-[#A9B2A3]">DATA</div>
        <a href="{{ route('personel.index') }}" class="nav-a {{ request()->routeIs('personel.*') ? 'on' : '' }}" @if (request()->routeIs('personel.*')) aria-current="page" @endif>@include('partials.ikon', ['n' => 'users'])Data Personel</a>
        <a href="{{ route('potongan.index') }}" class="nav-a {{ request()->routeIs('potongan.*') ? 'on' : '' }}" @if (request()->routeIs('potongan.*')) aria-current="page" @endif>@include('partials.ikon', ['n' => 'file'])Potongan Satuan</a>
        <a href="{{ route('rekap.index') }}" class="nav-a {{ request()->routeIs('rekap.*') ? 'on' : '' }}" @if (request()->routeIs('rekap.*')) aria-current="page" @endif>@include('partials.ikon', ['n' => 'chart'])Rekap &amp; Laporan</a>
    </div>

    <div class="mt-auto flex flex-col gap-2.5">
        <a href="{{ route('pengaturan') }}" class="nav-a {{ request()->routeIs('pengaturan') ? 'on' : '' }}" @if (request()->routeIs('pengaturan')) aria-current="page" @endif>@include('partials.ikon', ['n' => 'gear'])Pengaturan</a>
        <div class="flex items-center gap-2.5 border-t border-[#34422F] px-2.5 py-3">
            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-side-on text-xs font-semibold" aria-hidden="true">{{ $inisial }}</div>
            <div class="min-w-0 flex-1 text-[13px] font-medium">{{ $nama }}<span class="block text-xs font-normal text-[#A9B2A3]">Juru Bayar</span></div>
            <form method="post" action="{{ route('keluar') }}">
                @csrf
                <button type="submit" title="Keluar" aria-label="Keluar" class="flex h-9 w-9 cursor-pointer items-center justify-center rounded-md border-0 bg-transparent text-[#D5DACE] hover:bg-side-hover">@include('partials.ikon', ['n' => 'out'])</button>
            </form>
        </div>
    </div>
</nav>
