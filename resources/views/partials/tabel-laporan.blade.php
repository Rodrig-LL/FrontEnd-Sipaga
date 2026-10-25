{{-- Tabel laporan generik. Wajib: $rep, $rows --}}
<table class="t" style="min-width: 720px">
    <thead>
        <tr>
            @foreach ($rep['cols'] as $c)
                <th scope="col" class="{{ ! empty($c['r']) ? 'r' : '' }}">{{ $c['h'] }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            @if ($row['k'] === 'g')
                <tr><th scope="rowgroup" colspan="{{ count($rep['cols']) }}" class="!border-0 !bg-white !pb-1.5 !pt-4 text-[13px] tracking-[.06em] text-body">{{ $row['c'][0] }}</th></tr>
            @else
                <tr class="{{ $row['k'] }}">
                    @foreach ($row['c'] as $i => $sel)
                        <td class="{{ ! empty($rep['cols'][$i]['r']) ? 'r num' : (! empty($rep['cols'][$i]['t']) ? 'num' : '') }}">{{ $sel }}</td>
                    @endforeach
                </tr>
            @endif
        @endforeach
    </tbody>
    @if ($rep['tfoot'])
        <tfoot>
            <tr>
                @foreach ($rep['tfoot'] as $i => $sel)
                    <td class="{{ ! empty($rep['cols'][$i]['r']) ? 'r num' : (! empty($rep['cols'][$i]['t']) ? 'num' : '') }}">{{ $sel }}</td>
                @endforeach
            </tr>
        </tfoot>
    @endif
</table>
