@props(['title', 'active' => 'index'])
@php
    $links = [
        'index' => ['Prezentare', route('account.index')],
        'orders' => ['Comenzile mele', route('account.orders.index')],
        'profile' => ['Date personale', route('account.profile')],
        'password' => ['Parolă', route('account.password')],
        'security' => ['Securitate (2FA)', route('account.security')],
    ];
@endphp
<x-layouts.app :title="$title" robots="noindex">
    <div class="grid gap-8 md:grid-cols-4">
        <nav aria-label="Contul meu" class="md:col-span-1">
            <h2 class="mb-2 text-sm font-semibold uppercase tracking-wide text-zinc-500">Contul meu</h2>
            <ul class="space-y-1">
                @foreach ($links as $key => [$label, $url])
                    <li>
                        <a href="{{ $url }}" @if ($active === $key) aria-current="page" @endif
                           class="block rounded px-3 py-2 text-sm hover:bg-zinc-100 {{ $active === $key ? 'bg-brand-50 font-medium text-brand-700' : '' }}">{{ $label }}</a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="md:col-span-3">
            <h1 class="mb-6 text-2xl font-semibold">{{ $title }}</h1>
            {{ $slot }}
        </div>
    </div>
</x-layouts.app>
