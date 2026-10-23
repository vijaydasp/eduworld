@props(['name'=>'globe'])
<svg {{ $attributes->merge(['class'=>'icon']) }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
@switch($name)
@case('study')<path d="m2 8 10-5 10 5-10 5L2 8Z"/><path d="M6 10v6c4 3 8 3 12 0v-6M22 8v8"/>@break
@case('work')<rect x="3" y="7" width="18" height="14" rx="2"/><path d="M8 7V3h8v4M3 12c6 4 12 4 18 0M10 13h4v4h-4z"/>@break
@case('document')<path d="M14 2H5v20h14V7l-5-5ZM14 2v6h5M8 12h8M8 16h6"/>@break
@case('seal')<circle cx="12" cy="9" r="6"/><path d="m9 9 2 2 4-4M8 14l-2 8 6-3 6 3-2-8"/>@break
@case('people')<circle cx="9" cy="7" r="3"/><path d="M2 21v-3a7 7 0 0 1 14 0v3M16 4a3 3 0 0 1 0 6M19 15c2 1 3 3 3 6"/>@break
@case('arrow')<path d="M4 12h16m-6-6 6 6-6 6"/>@break
@case('check')<path d="m5 12 4 4L19 6"/>@break
@case('phone')<path d="M8 3 5 2 2 5c0 9 8 17 17 17l3-3-1-4-5-1-2 3c-4-2-5-3-7-7l3-2-2-5Z"/>@break
@case('mail')<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m2 5 10 8L22 5"/>@break
@case('pin')<path d="M20 10c0 6-8 12-8 12S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="3"/>@break
@case('chat')<path d="M21 11a9 9 0 0 1-13 8l-6 3 2-6A9 9 0 1 1 21 11Z"/><path d="M8 9h8M8 13h5"/>@break
@default <circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2c6 5 6 15 0 20-6-5-6-15 0-20Z"/>
@endswitch
</svg>
