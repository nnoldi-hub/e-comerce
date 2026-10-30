@php
    $user = auth()->user();
    $payment = old('payment_method', 'card');
    $shipping = (int) old('shipping_method_id', $shippingMethods->first()?->id);
@endphp
<x-layouts.app title="Finalizare comandă" robots="noindex">
    <h1 class="mb-6 text-2xl font-semibold">Finalizare comandă</h1>

    @if ($errors->has('stock') || $errors->has('cart'))
        <div role="alert" class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
            {{ $errors->first('stock') ?: $errors->first('cart') }}
        </div>
    @endif

    <form method="POST" action="{{ route('checkout.store') }}" class="grid gap-8 lg:grid-cols-3" novalidate>
        @csrf
        <input type="hidden" name="idempotency_key" value="{{ old('idempotency_key', $idempotencyKey) }}">
        <input type="hidden" name="country" value="RO">

        <div class="space-y-8 lg:col-span-2">
            <section aria-labelledby="h-contact">
                <h2 id="h-contact" class="mb-3 text-lg font-semibold">Date de contact și livrare</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-field name="first_name" label="Prenume" :value="$user?->name" required autocomplete="given-name" />
                    <x-field name="last_name" label="Nume" required autocomplete="family-name" />
                    <x-field name="email" type="email" label="Email" :value="$user?->email" required autocomplete="email" />
                    <x-field name="phone" type="tel" label="Telefon" :value="$user?->phone" required autocomplete="tel" />
                    <div class="sm:col-span-2"><x-field name="address_line1" label="Adresă" required autocomplete="address-line1" /></div>
                    <div class="sm:col-span-2"><x-field name="address_line2" label="Detalii adresă (bloc, scară, apartament)" autocomplete="address-line2" /></div>
                    <x-field name="city" label="Localitate" required autocomplete="address-level2" />
                    <x-field name="county" label="Județ" autocomplete="address-level1" />
                    <x-field name="postal_code" label="Cod poștal" required autocomplete="postal-code" />
                    <div>
                        <span class="mb-1 block text-sm font-medium">Țara</span>
                        <p class="rounded-lg border border-zinc-200 bg-zinc-50 px-3 py-2 text-sm">România</p>
                    </div>
                </div>
            </section>

            <section aria-labelledby="h-company">
                <label class="flex items-center gap-2 text-sm font-medium">
                    <input type="checkbox" name="is_company" value="1" class="peer size-4" @checked(old('is_company'))>
                    <span id="h-company">Comand ca firmă (persoană juridică)</span>
                </label>
                <div id="date-firma" class="mt-3 grid gap-4 sm:grid-cols-3">
                    <x-field name="company_name" label="Denumire firmă" autocomplete="organization" />
                    <x-field name="cui" label="CUI" hint="Ex. RO1234567" />
                    <x-field name="reg_com" label="Nr. Reg. Com." />
                </div>
                <p class="mt-1 text-xs text-zinc-500">Completează câmpurile doar dacă bifezi „Comand ca firmă”.</p>
            </section>

            <section aria-labelledby="h-ship">
                <h2 id="h-ship" class="mb-3 text-lg font-semibold">Metoda de livrare</h2>
                @error('shipping_method_id')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="space-y-2">
                    @forelse ($shippingMethods as $method)
                        <label class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 text-sm">
                            <input type="radio" name="shipping_method_id" value="{{ $method->id }}" @checked($shipping === $method->id)>
                            <span class="font-medium">{{ $method->name }}</span>
                        </label>
                    @empty
                        <p class="text-sm text-red-600">Nicio metodă de livrare nu este disponibilă momentan.</p>
                    @endforelse
                </div>
                @if ($totals->shippingError)<p class="mt-2 text-sm text-amber-700">{{ $totals->shippingError }}</p>@endif
            </section>

            <section aria-labelledby="h-pay">
                <h2 id="h-pay" class="mb-3 text-lg font-semibold">Metoda de plată</h2>
                @error('payment_method')<p class="mb-2 text-sm text-red-600">{{ $message }}</p>@enderror
                <div class="space-y-2">
                    <label class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 text-sm">
                        <input type="radio" name="payment_method" value="card" @checked($payment === 'card')>
                        <span class="font-medium">Card bancar</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-lg border border-zinc-200 p-3 text-sm">
                        <input type="radio" name="payment_method" value="cod" @checked($payment === 'cod')>
                        <span class="font-medium">Ramburs la livrare</span>
                    </label>
                </div>
            </section>

            <section>
                <label for="customer_note" class="mb-1 block text-sm font-medium">Observații (opțional)</label>
                <textarea id="customer_note" name="customer_note" rows="3" maxlength="1000" class="w-full rounded-lg border border-zinc-300 px-3 py-2 text-sm">{{ old('customer_note') }}</textarea>
            </section>
        </div>

        <aside class="lg:col-span-1">
            <div class="sticky top-4 space-y-4 rounded-xl border border-zinc-200 bg-zinc-50 p-5">
                <h2 class="text-lg font-semibold">Sumar</h2>

                <ul class="space-y-2 text-sm">
                    @foreach ($totals->lines as $line)
                        <li class="flex justify-between gap-3">
                            <span>{{ $line->name }} <span class="text-zinc-500">× {{ $line->quantity }}</span></span>
                            <x-price :amount="$line->lineTotal" :currency="$totals->currency" />
                        </li>
                    @endforeach
                </ul>

                <div>
                    <label for="coupon_code" class="mb-1 block text-sm font-medium">Cod de reducere</label>
                    <input id="coupon_code" name="coupon_code" value="{{ old('coupon_code') }}" maxlength="64" class="w-full rounded-lg border px-3 py-2 text-sm {{ $errors->has('coupon_code') ? 'border-red-500' : 'border-zinc-300' }}">
                    @error('coupon_code')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    <p class="mt-1 text-xs text-zinc-500">Reducerea se aplică la plasarea comenzii.</p>
                </div>

                <dl class="space-y-1 border-t border-zinc-200 pt-3 text-sm">
                    <div class="flex justify-between"><dt>Subtotal</dt><dd><x-price :amount="$totals->subtotal" :currency="$totals->currency" /></dd></div>
                    <div class="flex justify-between"><dt>Transport</dt><dd><x-price :amount="$totals->shipping" :currency="$totals->currency" /></dd></div>
                    @if ($totals->taxInclusive)
                        <div class="flex justify-between text-zinc-500"><dt>din care TVA</dt><dd><x-price :amount="$totals->tax" :currency="$totals->currency" /></dd></div>
                    @else
                        <div class="flex justify-between"><dt>TVA</dt><dd><x-price :amount="$totals->tax" :currency="$totals->currency" /></dd></div>
                    @endif
                    <div class="flex justify-between border-t border-zinc-200 pt-2 text-base font-semibold"><dt>Total</dt><dd><x-price :amount="$totals->grandTotal" :currency="$totals->currency" /></dd></div>
                </dl>

                <label class="flex items-start gap-2 text-sm">
                    <input type="checkbox" name="terms" value="1" class="mt-0.5 size-4" @checked(old('terms'))>
                    <span>Sunt de acord cu termenii și condițiile și cu politica de confidențialitate.</span>
                </label>
                @error('terms')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

                <x-button class="w-full py-3">Plasează comanda</x-button>
            </div>
        </aside>
    </form>
</x-layouts.app>
