@php($bag = $errors->getBag('updatePassword'))
<x-account.layout title="Schimbă parola" active="password">
    @if (session('status') === 'password-updated')
        <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">Parola a fost schimbată.</p>
    @endif

    <form method="POST" action="{{ url('/user/password') }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')
        @foreach ([['current_password', 'Parola curentă', 'current-password'], ['password', 'Parola nouă', 'new-password'], ['password_confirmation', 'Confirmă parola nouă', 'new-password']] as [$field, $label, $auto])
            <div>
                <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="password" required autocomplete="{{ $auto }}"
                       class="w-full rounded-lg border px-3 py-2 text-sm {{ $bag->has($field) ? 'border-red-500' : 'border-zinc-300' }}">
                @if ($bag->has($field))<p class="mt-1 text-sm text-red-600">{{ $bag->first($field) }}</p>@endif
            </div>
        @endforeach
        <x-button>Schimbă parola</x-button>
    </form>
</x-account.layout>
