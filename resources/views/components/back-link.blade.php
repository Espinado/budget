@props(['href'])

<a href="{{ $href }}" {{ $attributes->merge(['class' => 'inline-flex items-center rounded-2xl bg-sky-100 px-3 py-2 text-base font-medium text-sky-900 ring-1 ring-sky-200 hover:bg-sky-200']) }}>{{ $slot }}</a>
