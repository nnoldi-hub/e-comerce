<x-account.layout :title="'Salut, '.$user->name" active="index">
    @unless ($user->hasVerifiedEmail())
        <div role="status" class="mb-6 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p>Adresa de email <strong>{{ $user->email }}</strong> nu este confirmată încă.</p>
            <form method="POST" action="{{ url('/email/verification-notification') }}" class="mt-2">
                @csrf
                <button type="submit" class="font-medium underline">Retrimite emailul de confirmare</button>
            </form>
        </div>
    @endunless

    <section aria-labelledby="h-last">
        <div class="mb-3 flex items-baseline justify-between">
            <h2 id="h-last" class="text-lg font-semibold">Ultimele comenzi</h2>
            <a href="{{ route('account.orders.index') }}" class="text-sm text-brand-600 hover:underline">Vezi toate</a>
        </div>

        @forelse ($orders as $order)
            <a href="{{ route('account.orders.show', $order->number) }}" class="mb-2 flex items-center justify-between rounded-lg border border-zinc-200 p-4 hover:bg-zinc-50">
                <span>
                    <span class="font-medium">{{ $order->number }}</span>
                    <span class="ml-2 text-sm text-zinc-500">{{ $order->placed_at?->format('d.m.Y') }}</span>
                </span>
                <span class="flex items-center gap-3">
                    <x-order-status :status="$order->status" />
                    <x-price :amount="$order->grand_total" :currency="$order->currency" />
                </span>
            </a>
        @empty
            <p class="rounded-lg border border-dashed border-zinc-300 p-6 text-center text-sm text-zinc-500">Nu ai nicio comandă încă.</p>
        @endforelse
    </section>

    <section class="mt-8 rounded-xl border border-zinc-200 p-4 text-sm" aria-labelledby="h-data">
        <h2 id="h-data" class="mb-2 text-lg font-semibold">Date de contact</h2>
        <p>{{ $user->name }}</p>
        <p>{{ $user->email }}</p>
        <p>{{ $user->phone ?: 'Telefon necompletat' }}</p>
        <a href="{{ route('account.profile') }}" class="mt-2 inline-block text-brand-600 hover:underline">Modifică</a>
    </section>
</x-account.layout>
