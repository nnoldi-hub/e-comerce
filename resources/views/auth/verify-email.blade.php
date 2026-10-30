<x-layouts.app title="Confirmă emailul" robots="noindex">
    <x-auth-card title="Confirmă adresa de email">
        @if (session('status') === 'verification-link-sent')
            <p class="mb-4 rounded-lg bg-green-50 px-3 py-2 text-sm text-green-800" role="status">Ți-am trimis un nou link de confirmare.</p>
        @endif
        <p class="mb-4 text-sm text-zinc-600">Verifică-ți căsuța de email și apasă pe linkul primit. Dacă nu l-ai primit, îți trimitem altul.</p>
        <form method="POST" action="{{ url('/email/verification-notification') }}">
            @csrf
            <x-button class="w-full">Retrimite emailul</x-button>
        </form>
    </x-auth-card>
</x-layouts.app>
