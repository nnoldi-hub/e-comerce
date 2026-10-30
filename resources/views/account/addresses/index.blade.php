<x-account.layout title="Adresele mele" active="addresses">
    @php($flash = session('status'))
    @if ($flash === 'address-saved')
        <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">Adresa a fost salvată.</p>
    @elseif ($flash === 'address-deleted')
        <p role="status" class="mb-4 rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm">Adresa a fost ștearsă.</p>
    @elseif ($flash === 'address-default')
        <p role="status" class="mb-4 rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">Adresa implicită a fost schimbată.</p>
    @endif
    @error('limit')<p role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</p>@enderror

    <p class="mb-4 max-w-xl text-sm text-zinc-600">Adresele salvate se precompletează la finalizarea comenzii. Comenzile deja plasate își păstrează adresa de la momentul comenzii.</p>

    @if ($addresses->isEmpty())
        <p class="rounded-lg border border-dashed border-zinc-300 p-8 text-center text-sm text-zinc-500">Nu ai nicio adresă salvată.</p>
    @else
        <ul class="grid gap-4 sm:grid-cols-2">
            @foreach ($addresses as $address)
                <li class="rounded-xl border border-zinc-200 p-4 text-sm">
                    <p class="mb-1 flex items-center gap-2 font-semibold">
                        {{ $address->label ?: trim($address->first_name.' '.$address->last_name) }}
                        @if ($address->is_default)<span class="rounded-full bg-brand-50 px-2 py-0.5 text-xs font-medium text-brand-700">Implicită</span>@endif
                    </p>
                    <p class="whitespace-pre-line text-zinc-700">{{ implode("\n", \App\Support\AddressFormatter::lines($address->only(\App\Models\Address::FIELDS))) }}</p>

                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <a href="{{ route('account.addresses.edit', $address->id) }}" class="text-brand-600 hover:underline">Modifică</a>
                        @unless ($address->is_default)
                            <form method="POST" action="{{ route('account.addresses.default', $address->id) }}">
                                @csrf
                                <button type="submit" class="text-brand-600 hover:underline">Setează ca implicită</button>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('account.addresses.destroy', $address->id) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="text-red-600 hover:underline">Șterge</button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif

    @unless ($full)
        <p class="mt-6"><a href="{{ route('account.addresses.create') }}" class="inline-block rounded-lg bg-brand-600 px-4 py-2 text-sm font-medium text-white hover:bg-brand-700">Adaugă o adresă</a></p>
    @endunless
</x-account.layout>
