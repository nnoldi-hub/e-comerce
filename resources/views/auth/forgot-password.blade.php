<x-layouts.app title="Parolă uitată" robots="noindex">
    <x-auth-card title="Resetare parolă">
        @if (session('status'))
            <p class="mb-4 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800" role="status">{{ session('status') }}</p>
        @endif
        <p class="mb-4 text-sm text-zinc-600">Introdu adresa de email și îți trimitem un link de resetare.</p>
        <form method="POST" action="{{ url('/forgot-password') }}" class="space-y-4">
            @csrf
            <x-field name="email" type="email" label="Email" required autocomplete="email" />
            <x-button class="w-full">Trimite linkul</x-button>
        </form>
    </x-auth-card>
</x-layouts.app>
