<x-layouts.app title="Confirmă parola" robots="noindex">
    <x-auth-card title="Confirmă parola">
        <p class="mb-4 text-sm text-zinc-600">Pentru siguranță, confirmă parola înainte de a continua.</p>
        <form method="POST" action="{{ url('/user/confirm-password') }}" class="space-y-4">
            @csrf
            <x-field name="password" type="password" label="Parolă" required autocomplete="current-password" />
            <x-button class="w-full">Confirmă</x-button>
        </form>
    </x-auth-card>
</x-layouts.app>
