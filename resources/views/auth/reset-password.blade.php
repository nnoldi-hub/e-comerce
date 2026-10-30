<x-layouts.app title="Parolă nouă" robots="noindex">
    <x-auth-card title="Alege o parolă nouă">
        <form method="POST" action="{{ url('/reset-password') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="token" value="{{ request()->route('token') }}">
            <x-field name="email" type="email" label="Email" :value="request()->query('email')" required autocomplete="username" />
            <x-field name="password" type="password" label="Parolă nouă" required autocomplete="new-password" />
            <x-field name="password_confirmation" type="password" label="Confirmă parola" required autocomplete="new-password" />
            <x-button class="w-full">Salvează parola</x-button>
        </form>
    </x-auth-card>
</x-layouts.app>
