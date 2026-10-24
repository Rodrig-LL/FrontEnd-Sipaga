{{-- Tampilan saat belum ada proses gaji yang selesai. Opsional: $judul --}}
<section class="card max-w-[720px] !px-6 !py-[22px]">
    <h2 class="!text-lg">Belum ada gaji yang diproses</h2>
    <p class="muted my-2 leading-normal">{{ $judul ?? 'Halaman ini' }} terisi setelah PDF DPP dan pembukuan bulan lalu diproses.</p>
    <a class="btn btn-p btn-lg" href="{{ route('gaji.index') }}">Mulai proses gaji</a>
</section>
