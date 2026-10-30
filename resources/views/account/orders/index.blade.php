<x-account.layout title="Comenzile mele" active="orders">
    @if ($orders->isEmpty())
        <p class="rounded-lg border border-dashed border-zinc-300 p-8 text-center text-zinc-500">
            Nu ai nicio comandă încă. <a href="{{ route('catalog.index') }}" class="text-brand-600 hover:underline">Descoperă produsele</a>.
        </p>
    @else
        <div class="overflow-x-auto rounded-xl border border-zinc-200">
            <table class="w-full text-sm">
                <caption class="sr-only">Comenzile mele</caption>
                <thead class="bg-zinc-50 text-left text-zinc-500">
                    <tr>
                        <th scope="col" class="px-4 py-2 font-medium">Comandă</th>
                        <th scope="col" class="px-4 py-2 font-medium">Data</th>
                        <th scope="col" class="px-4 py-2 font-medium">Status</th>
                        <th scope="col" class="px-4 py-2 text-right font-medium">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-zinc-100">
                    @foreach ($orders as $order)
                        <tr>
                            <td class="px-4 py-3"><a href="{{ route('account.orders.show', $order->number) }}" class="font-medium text-brand-600 hover:underline">{{ $order->number }}</a></td>
                            <td class="px-4 py-3">{{ $order->placed_at?->format('d.m.Y H:i') }}</td>
                            <td class="px-4 py-3"><x-order-status :status="$order->status" /></td>
                            <td class="px-4 py-3 text-right"><x-price :amount="$order->grand_total" :currency="$order->currency" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-6">{{ $orders->links() }}</div>
    @endif
</x-account.layout>
