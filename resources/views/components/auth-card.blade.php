@props(['title'])
<div class="mx-auto max-w-md">
    <h1 class="mb-6 text-2xl font-semibold">{{ $title }}</h1>
    <div class="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm">
        {{ $slot }}
    </div>
</div>
