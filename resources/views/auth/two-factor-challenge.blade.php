<x-layouts.app title="Verificare în doi pași" robots="noindex">
    <x-auth-card title="Verificare în doi pași">
        <form method="POST" action="{{ url('/two-factor-challenge') }}" class="space-y-4">
            @csrf
            <x-field name="code" label="Codul din aplicația de autentificare" autocomplete="one-time-code" inputmode="numeric" />
            <p class="text-center text-xs text-zinc-500">sau</p>
            <x-field name="recovery_code" label="Cod de recuperare" autocomplete="one-time-code" />
            <x-button class="w-full">Continuă</x-button>
        </form>
    </x-auth-card>
</x-layouts.app>
