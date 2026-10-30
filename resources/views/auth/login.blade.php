<x-layouts.app title="Autentificare" robots="noindex">
    <x-auth-card title="Autentificare">
        <form method="POST" action="{{ url('/login') }}" class="space-y-4">
            @csrf
            <x-field name="email" type="email" label="Email" required autocomplete="username" />
            <x-field name="password" type="password" label="Parolă" required autocomplete="current-password" />
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="remember" value="1" class="size-4"> Ține-mă minte</label>
            <x-button class="w-full">Intră în cont</x-button>
        </form>
        <div class="mt-4 flex justify-between text-sm">
            <a href="{{ url('/forgot-password') }}" class="text-brand-600 hover:underline">Ai uitat parola?</a>
            <a href="{{ url('/register') }}" class="text-brand-600 hover:underline">Creează cont</a>
        </div>
    </x-auth-card>
</x-layouts.app>
