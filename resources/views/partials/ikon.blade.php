@php
    $attr = 'aria-hidden="true" width="'.($u ?? 18).'" height="'.($u ?? 18).'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="'.($w ?? 1.8).'" stroke-linecap="round" stroke-linejoin="round"';
@endphp
@if ($n === 'home')<svg {!! $attr !!}><path d="M3 10.5 12 3l9 7.5V21h-6v-6H9v6H3z"/></svg>
@elseif ($n === 'up')<svg {!! $attr !!}><path d="M12 16V4M7 9l5-5 5 5M4 20h16"/></svg>
@elseif ($n === 'doc')<svg {!! $attr !!}><path d="M14 3H6v18h12V7zM14 3v4h4M9 12h6M9 16h6"/></svg>
@elseif ($n === 'users')<svg {!! $attr !!}><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20a6.5 6.5 0 0 1 13 0M16 4.5a3.5 3.5 0 0 1 0 7M18 14a6 6 0 0 1 3.5 6"/></svg>
@elseif ($n === 'chart')<svg {!! $attr !!}><path d="M5 20V11M11 20V5M17 20v-7M3 20h18"/></svg>
@elseif ($n === 'gear')<svg {!! $attr !!}><circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M4.9 4.9 7 7M17 17l2.1 2.1M4.9 19.1 7 17M17 7l2.1-2.1"/></svg>
@elseif ($n === 'out')<svg {!! $attr !!}><path d="M15 4h4v16h-4M10 8l-4 4 4 4M6 12h10"/></svg>
@elseif ($n === 'back')<svg {!! $attr !!} stroke-width="2"><path d="M19 12H5M11 6l-6 6 6 6"/></svg>
@elseif ($n === 'err')<svg {!! $attr !!} stroke-width="2.2"><circle cx="12" cy="12" r="9"/><path d="M12 7.5v5.5M12 16.5v.01"/></svg>
@elseif ($n === 'ok')<svg {!! $attr !!} stroke-width="2.6"><path d="M5 12.5 10 17l9-10"/></svg>
@elseif ($n === 'file')<svg {!! $attr !!} stroke-width="1.6"><path d="M14 3H6v18h12V7zM14 3v4h4M9 13l2 2 4-4"/></svg>
@endif
