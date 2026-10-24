<section class="card max-w-[720px] !px-6 !py-[22px]">
    <h2 class="!text-lg">Ringkasan sebelum terbit</h2>
    <div class="my-3.5 flex flex-col text-base">
        <div class="flex justify-between gap-3 border-b border-[#DAD8D0] py-2.5"><span>Cocok otomatis</span><span class="font-mono">486</span></div>
        <div class="flex justify-between gap-3 border-b border-[#DAD8D0] py-2.5"><span>Selisih yang sudah diputuskan</span><span class="font-mono">{{ $putus }} dari 19</span></div>
        <div class="flex justify-between gap-3 border-b border-[#DAD8D0] py-2.5"><span>Data tidak cocok yang sudah diselesaikan</span><span class="font-mono">{{ $fix }} dari 7</span></div>
        <div class="flex justify-between gap-3 pt-3 text-lg font-semibold"><span>Slip yang akan terbit</span><span class="font-mono">{{ $terbit }}</span></div>
    </div>

    @if ($ditunda > 0)
        <p class="mb-4 flex gap-2 text-[15px]"><span class="mt-0.5 flex-none text-alert">@include('partials.ikon', ['n' => 'err', 'u' => 16])</span><span><strong>{{ $ditunda }} slip ditunda</strong> karena datanya belum diputuskan. Slip itu bisa diterbitkan nanti setelah dilengkapi.</span></p>
    @endif

    @if ($st['published'])
        <div class="note" role="status"><strong>Slip sedang dibuat di server.</strong> Halaman ini boleh ditutup; prosesnya tetap berjalan. Slip yang sudah jadi langsung muncul di Daftar Slip.</div>
        <p class="mt-3.5"><a class="btn btn-p btn-lg" href="{{ route('slip.index') }}">Buka Daftar Slip</a></p>
    @else
        <form method="post" action="{{ route('proses.aksi') }}">
            @csrf
            <input type="hidden" name="do" value="publish">
            <button type="submit" class="btn btn-p btn-lg">Terbitkan {{ $terbit }} slip</button>
        </form>
    @endif
</section>
