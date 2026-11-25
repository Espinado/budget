@props(['large' => false])

<a href="{{ auth()->check() ? route('dashboard') : route('home') }}" {{ $attributes->merge(['class' => 'inline-flex items-center gap-2 font-semibold tracking-tight text-ink']) }}>
    <img src="/icons/family-money-192.png" alt="" class="{{ $large ? 'h-16 w-16' : 'h-9 w-9 sm:h-10 sm:w-10' }} rounded-full">
    <span class="{{ $large ? 'text-2xl sm:text-3xl' : 'text-xl sm:text-2xl' }}">Family Money</span>
</a>
