@php($confirmBag = $errors->getBag('confirmTwoFactorAuthentication'))
<x-account.layout title="Securitate" active="security">
    @php($flash = session('status'))
    @if ($flash === 'two-factor-authentication-enabled')
        <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">Scanează codul QR și confirmă cu un cod din aplicație.</p>
    @elseif ($flash === 'two-factor-authentication-confirmed')
        <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">Verificarea în doi pași este activă. Păstrează codurile de recuperare.</p>
    @elseif ($flash === 'two-factor-authentication-disabled')
        <p role="status" class="mb-4 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm">Verificarea în doi pași a fost dezactivată.</p>
    @elseif ($flash === 'recovery-codes-generated')
        <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">Au fost generate coduri noi de recuperare.</p>
    @endif

    <p class="mb-6 max-w-xl text-sm text-zinc-600">Verificarea în doi pași adaugă un cod din aplicația de autentificare (Google Authenticator, Authy etc.) la parolă, ca să-ți protejeze comenzile și datele.</p>

    @if ($enabled)
        <p class="mb-4 font-medium text-green-700">Verificarea în doi pași este activă.</p>

        <section class="mb-6 max-w-md" aria-labelledby="h-rec">
            <h2 id="h-rec" class="mb-2 text-lg font-semibold">Coduri de recuperare</h2>
            <p class="mb-2 text-sm text-zinc-600">Fiecare cod poate fi folosit o singură dată dacă pierzi accesul la aplicație.</p>
            <ul class="grid grid-cols-2 gap-2 rounded-lg bg-zinc-50 p-3 font-mono text-sm">
                @foreach ($recoveryCodes as $code)
                    <li>{{ $code }}</li>
                @endforeach
            </ul>
            <form method="POST" action="{{ url('/user/two-factor-recovery-codes') }}" class="mt-3">
                @csrf
                <x-button variant="secondary">Generează coduri noi</x-button>
            </form>
        </section>

        <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
            @csrf
            @method('DELETE')
            <x-button variant="secondary">Dezactivează verificarea în doi pași</x-button>
        </form>
    @elseif ($pending)
        <section class="max-w-md space-y-4" aria-labelledby="h-setup">
            <h2 id="h-setup" class="text-lg font-semibold">Termină configurarea</h2>
            <div class="inline-block rounded-lg border border-zinc-200 bg-white p-3">{!! $qrCode !!}</div>
            @if ($setupKey)
                <p class="text-sm text-zinc-600">Nu poți scana? Introdu cheia manual: <code class="rounded bg-zinc-100 px-1.5 py-0.5">{{ $setupKey }}</code></p>
            @endif

            <form method="POST" action="{{ url('/user/confirmed-two-factor-authentication') }}" class="space-y-3">
                @csrf
                <div>
                    <label for="code" class="mb-1 block text-sm font-medium">Cod din aplicație</label>
                    <input id="code" name="code" inputmode="numeric" autocomplete="one-time-code" required
                           class="w-full rounded-lg border px-3 py-2 text-sm {{ $confirmBag->has('code') ? 'border-red-500' : 'border-zinc-300' }}">
                    @if ($confirmBag->has('code'))<p class="mt-1 text-sm text-red-600">{{ $confirmBag->first('code') }}</p>@endif
                </div>
                <x-button>Confirmă și activează</x-button>
            </form>

            <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
                @csrf
                @method('DELETE')
                <button type="submit" class="text-sm text-zinc-600 hover:underline">Anulează</button>
            </form>
        </section>
    @else
        <form method="POST" action="{{ url('/user/two-factor-authentication') }}">
            @csrf
            <x-button>Activează verificarea în doi pași</x-button>
        </form>
    @endif
</x-account.layout>
