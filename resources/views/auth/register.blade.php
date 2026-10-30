<x-layouts.app title="Creare cont" robots="noindex">
    <x-auth-card title="Creează un cont">
        <form method="POST" action="{{ url('/register') }}" class="space-y-4">
            @csrf
            <x-field name="name" label="Nume complet" required autocomplete="name" />
            <x-field name="email" type="email" label="Email" required autocomplete="username" />
            <x-field name="password" type="password" label="Parolă" required autocomplete="new-password" hint="Minimum 8 caractere." />
            <x-field name="password_confirmation" type="password" label="Confirmă parola" required autocomplete="new-password" />
            <x-button class="w-full">Creează cont</x-button>
        </form>
        <p class="mt-4 text-sm">Ai deja cont? <a href="{{ route('login') }}" class="text-brand-600 hover:underline">Autentifică-te</a></p>
    </x-auth-card>
</x-layouts.app>
