@php($bag = $errors->getBag('updateProfileInformation'))
<x-account.layout title="Date personale" active="profile">
    @if (session('status') === 'profile-information-updated')
        <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">Datele au fost salvate.</p>
    @endif

    <form method="POST" action="{{ url('/user/profile-information') }}" class="max-w-md space-y-4">
        @csrf
        @method('PUT')
        @foreach ([['name', 'Nume complet', 'text', $user->name, 'name'], ['email', 'Email', 'email', $user->email, 'email'], ['phone', 'Telefon', 'tel', $user->phone, 'tel']] as [$field, $label, $type, $value, $auto])
            <div>
                <label for="{{ $field }}" class="mb-1 block text-sm font-medium">{{ $label }}</label>
                <input id="{{ $field }}" name="{{ $field }}" type="{{ $type }}" value="{{ old($field, $value) }}" autocomplete="{{ $auto }}"
                       @if ($field !== 'phone') required @endif
                       class="w-full rounded-lg border px-3 py-2 text-sm {{ $bag->has($field) ? 'border-red-500' : 'border-zinc-300' }}">
                @if ($bag->has($field))<p class="mt-1 text-sm text-red-600">{{ $bag->first($field) }}</p>@endif
            </div>
        @endforeach
        <p class="text-xs text-zinc-500">Dacă îți schimbi emailul, va trebui să îl confirmi din nou.</p>
        <x-button>Salvează</x-button>
    </form>
</x-account.layout>
