@php
    $editing = $address->exists;
    $company = old('is_company', filled($address->company_name));
    $val = fn (string $key) => $address->{$key};
@endphp
<x-account.layout :title="$editing ? 'Modifică adresa' : 'Adresă nouă'" active="addresses">
    <form method="POST" action="{{ $editing ? route('account.addresses.update', $address->id) : route('account.addresses.store') }}" class="max-w-2xl space-y-4" novalidate>
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="country" value="RO">

        <x-field name="label" label="Nume adresă (opțional)" :value="$val('label')" hint="Ex. Acasă, Birou" autocomplete="off" />

        <div class="grid gap-4 sm:grid-cols-2">
            <x-field name="first_name" label="Prenume" :value="$val('first_name')" required autocomplete="given-name" />
            <x-field name="last_name" label="Nume" :value="$val('last_name')" required autocomplete="family-name" />
            <x-field name="phone" type="tel" label="Telefon" :value="$val('phone')" required autocomplete="tel" />
            <div></div>
            <div class="sm:col-span-2"><x-field name="address_line1" label="Adresă" :value="$val('address_line1')" required autocomplete="address-line1" /></div>
            <div class="sm:col-span-2"><x-field name="address_line2" label="Detalii adresă (bloc, scară, apartament)" :value="$val('address_line2')" autocomplete="address-line2" /></div>
            <x-field name="city" label="Localitate" :value="$val('city')" required autocomplete="address-level2" />
            <x-field name="county" label="Județ" :value="$val('county')" autocomplete="address-level1" />
            <x-field name="postal_code" label="Cod poștal" :value="$val('postal_code')" required autocomplete="postal-code" />
            <div>
                <span class="mb-1 block text-sm font-medium">Țara</span>
                <p class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm">România</p>
            </div>
        </div>

        <div>
            <input id="is_company" type="checkbox" name="is_company" value="1" class="peer size-4 align-middle" @checked($company)>
            <label for="is_company" class="ml-2 align-middle text-sm font-medium">Adresă de firmă (persoană juridică)</label>

            <div class="mt-3 hidden gap-4 peer-checked:grid sm:grid-cols-3">
                <x-field name="company_name" label="Denumire firmă" :value="$val('company_name')" autocomplete="off" />
                <x-field name="cui" label="CUI" :value="$val('cui')" hint="Ex. RO1234567" autocomplete="off" />
                <x-field name="reg_com" label="Nr. Reg. Com." :value="$val('reg_com')" autocomplete="off" />
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" name="is_default" value="1" class="size-4" @checked(old('is_default', $address->is_default))>
            Folosește ca adresă implicită
        </label>

        <div class="flex items-center gap-4">
            <x-button>Salvează adresa</x-button>
            <a href="{{ route('account.addresses.index') }}" class="text-sm text-zinc-600 hover:underline">Renunță</a>
        </div>
    </form>
</x-account.layout>
