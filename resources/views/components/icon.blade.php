@props(['name' => 'dot'])

<svg {{ $attributes->merge(['class' => 'h-4 w-4']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
    @switch($name)
        @case('wallet')
            <ellipse cx="12" cy="17" rx="6.5" ry="2.4" />
            <path d="M5.5 17v-3c0 1.3 2.9 2.4 6.5 2.4s6.5-1.1 6.5-2.4v3" />
            <ellipse cx="12" cy="12" rx="6.5" ry="2.4" />
            <path d="M5.5 12V9c0 1.3 2.9 2.4 6.5 2.4s6.5-1.1 6.5-2.4v3" />
            <ellipse cx="12" cy="7" rx="6.5" ry="2.4" />
            @break
        @case('bank')
            <path d="M12 3 3 8h18L12 3Z" />
            <path d="M5 10v7M10 10v7M14 10v7M19 10v7" />
            <path d="M3 20h18M4 17h16" />
            @break
        @case('card')
            <rect x="3" y="5" width="18" height="14" rx="2" />
            <rect x="6" y="8.5" width="3.2" height="2.6" rx="0.4" />
            <path d="M3 14.5h18" />
            @break
        @case('home')
            <path d="M4 11 12 4l8 7" />
            <path d="M6 10.5V20h12v-9.5" />
            <path d="M10 20v-5h4v5" />
            @break
        @case('signal')
            <path d="M5 15a10 10 0 0 1 14 0" />
            <path d="M8 18a6 6 0 0 1 8 0" />
            <path d="M12 21h.01" />
            @break
        @case('coins')
            <ellipse cx="12" cy="7" rx="7" ry="3" />
            <path d="M5 7v4.5c0 1.7 3.1 3 7 3s7-1.3 7-3V7" />
            <path d="M5 11.5V16c0 1.7 3.1 3 7 3s7-1.3 7-3v-4.5" />
            @break
        @case('salary')
            <path d="M16.5 6.5a6.2 6.2 0 1 0 0 11" />
            <path d="M5 10.5h8.5M5 13.5h8.5" />
            @break
        @case('smoke')
            <path d="M4 13h11a2 2 0 0 0 2-2V9" />
            <path d="M4 13v2h9" />
            <path d="M15 13h4" />
            <path d="M18 9c.6-1.4 1.6-2 2.5-3" />
            @break
        @case('bag')
            <path d="M6 8h12l-1.2 12H7.2L6 8Z" />
            <path d="M9 8V6.5a3 3 0 0 1 6 0V8" />
            @break
        @case('plus')
            <path d="M12 5v14M5 12h14" />
            @break
        @case('calendar')
            <rect x="4" y="5" width="16" height="15" rx="2" />
            <path d="M8 3v4M16 3v4M4 10h16" />
            @break
        @case('in')
            <path d="M12 4v11" />
            <path d="m7 10 5 5 5-5" />
            <path d="M5 20h14" />
            @break
        @case('out')
            <path d="M12 20V9" />
            <path d="m7 14 5-5 5 5" />
            <path d="M5 4h14" />
            @break
        @case('phone')
            <rect x="7" y="3" width="10" height="18" rx="2" />
            <path d="M11 18h2" />
            @break
        @case('wifi')
            <path d="M4 10a12 12 0 0 1 16 0" />
            <path d="M7.5 13.5a7 7 0 0 1 9 0" />
            <path d="M12 18h.01" />
            @break
        @case('bolt')
            <path d="M13 3 5 14h6l-1 7 8-11h-6l1-7Z" />
            @break
        @case('flame')
            <path d="M12 21c4 0 6-2.8 6-6.2 0-2.4-1.2-4-2.4-5.2.2 2.2-1 3.4-1 3.4C16 9 13.5 4 12 3 11 6 8 8.2 8 12.2 8 17 9.6 21 12 21Z" />
            @break
        @case('drop')
            <path d="M12 3s6 6.2 6 10.5A6 6 0 0 1 6 13.5C6 9.2 12 3 12 3Z" />
            @break
        @case('building')
            <path d="M4 20V6l8-3 8 3v14" />
            <path d="M9 20v-4h6v4M9 9h.01M12 9h.01M15 9h.01M9 13h.01M12 13h.01M15 13h.01" />
            @break
        @case('utensils')
            <path d="M8 3v8M6 3v4a2 2 0 0 0 4 0V3M8 11v10M16 3c1.5 2 2 4 2 6s-1 3-2 3v9" />
            @break
        @case('cup')
            <path d="M6 8h10v5a4 4 0 0 1-4 4H10a4 4 0 0 1-4-4V8Z" />
            <path d="M16 9h2.5a2.5 2.5 0 0 1 0 5H16M6 20h10" />
            @break
        @case('car')
            <path d="M4 15h16M6 15l1.5-5h9L18 15" />
            <path d="M5 15v3h2.5M16.5 18H19v-3" />
            <circle cx="7.5" cy="16.5" r="1.2" />
            <circle cx="16.5" cy="16.5" r="1.2" />
            @break
        @case('fuel')
            <rect x="4" y="4" width="10" height="16" rx="1.5" />
            <path d="M14 10h2.5a2 2 0 0 1 2 2v6a1.5 1.5 0 0 0 3 0V9l-2.5-2.5" />
            <path d="M7 8h4" />
            @break
        @case('piggy')
            <path d="M6 11c0-3 2.4-5 6-5h2.5c2 0 3.5 1.4 3.5 3.2 0 1.2-.6 2.2-1.5 2.8H8.2A3.2 3.2 0 0 0 6 15v1H4v-3.2A2 2 0 0 1 6 11Z" />
            <path d="M8 18v2M15 18v2M16 10.5h.01" />
            @break
        @case('shield')
            <path d="M12 3 5 6v6c0 4.2 2.8 7 7 9 4.2-2 7-4.8 7-9V6l-7-3Z" />
            <path d="m9 12 2 2 4-4" />
            @break
        @case('heart')
            <path d="M12 20s-7-4.4-7-9a4 4 0 0 1 7-2 4 4 0 0 1 7 2c0 4.6-7 9-7 9Z" />
            @break
        @case('pill')
            <rect x="3" y="9" width="18" height="6" rx="3" transform="rotate(-35 12 12)" />
            <path d="m10 8 6 8" />
            @break
        @case('shirt')
            <path d="M8 5 4 8l2 3 2-1.5V20h8V9.5L18 11l2-3-4-3-2 2h-4L8 5Z" />
            @break
        @case('baby')
            <circle cx="12" cy="8" r="3" />
            <path d="M6 20c.6-3.2 2.8-5 6-5s5.4 1.8 6 5" />
            <path d="M9 8c-.4-2 0-3.5 1.5-4M15 8c.4-2 0-3.5-1.5-4" />
            @break
        @case('paw')
            <circle cx="7" cy="8" r="1.6" />
            <circle cx="12" cy="6" r="1.6" />
            <circle cx="17" cy="8" r="1.6" />
            <ellipse cx="12" cy="15" rx="3.4" ry="2.6" />
            @break
        @case('book')
            <path d="M5 5.5A2.5 2.5 0 0 1 7.5 3H19v16H7.5A2.5 2.5 0 0 0 5 21.5Z" />
            <path d="M5 5.5V19" />
            @break
        @case('sport')
            <circle cx="12" cy="12" r="8" />
            <path d="M12 4c2 2.2 3 5 3 8s-1 5.8-3 8M12 4c-2 2.2-3 5-3 8s1 5.8 3 8M4.5 9h15M4.5 15h15" />
            @break
        @case('plane')
            <path d="M3 12h7l8-6v12l-8-6H3l2 3" />
            @break
        @case('fun')
            <path d="M5 8h14v3a2 2 0 0 0 0 2v3H5v-3a2 2 0 0 0 0-2V8Z" />
            <path d="M12 8v8" />
            @break
        @case('gift')
            <rect x="4" y="10" width="16" height="10" rx="1" />
            <path d="M4 14h16M12 10v10M12 10c-2 0-4-1.4-4-3s2-2 4 1c2-3 4-2.6 4-1s-2 3-4 3Z" />
            @break
        @case('cart')
            <path d="M4 6h2l2.2 9h9.2l2-6H8" />
            <circle cx="10" cy="19" r="1.3" />
            <circle cx="17" cy="19" r="1.3" />
            @break
        @default
            <circle cx="12" cy="12" r="3" />
    @endswitch
</svg>
