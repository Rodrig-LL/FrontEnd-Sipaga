{{-- Paginasi dengan kotak nomor halaman. Wajib: $hal (paginator), $label. Opsional: $galat --}}
<nav class="flex flex-wrap items-center justify-between gap-3 px-3.5 py-3" aria-label="Halaman {{ $label }}">
    <span class="muted">Menampilkan {{ $hal->firstItem() ?? 0 }}–{{ $hal->lastItem() ?? 0 }} dari {{ $hal->total() }} {{ $label }}</span>
    <form method="get" action="{{ $hal->path() }}" class="flex flex-wrap items-center gap-2">
        @foreach (request()->except('halaman') as $k => $v)
            @if (! is_array($v))<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif
        @endforeach
        @if ($hal->onFirstPage())
            <span class="btn off" aria-disabled="true">Sebelumnya</span>
        @else
            <a class="btn btn-o" href="{{ $hal->previousPageUrl() }}">Sebelumnya</a>
        @endif
        <label for="halaman" class="pl-1.5 text-[15px]">Halaman</label>
        <input id="halaman" name="halaman" class="pgin" type="text" inputmode="numeric" autocomplete="off" value="{{ $hal->currentPage() }}" aria-describedby="halaman-total">
        <span id="halaman-total">dari {{ $hal->lastPage() }}</span>
        <button type="submit" class="btn btn-p">Buka</button>
        @if ($hal->hasMorePages())
            <a class="btn btn-o" href="{{ $hal->nextPageUrl() }}">Berikutnya</a>
        @else
            <span class="btn off" aria-disabled="true">Berikutnya</span>
        @endif
    </form>
    @if (! empty($galat))
        <p role="alert" class="basis-full text-right text-sm font-semibold text-alert">{{ $galat }}</p>
    @endif
</nav>
