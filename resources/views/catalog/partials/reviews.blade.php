@props(['product', 'reviews', 'distribution', 'myReview'])
@php
    $count = (int) data_get($product, 'reviews_count', 0);
    $average = (float) data_get($product, 'rating_avg', 0);
    $author = function ($review): string {
        $parts = preg_split('/\s+/', trim((string) data_get($review->user, 'name', 'Client'))) ?: ['Client'];

        return count($parts) > 1 ? $parts[0].' '.mb_strtoupper(mb_substr(end($parts), 0, 1)).'.' : $parts[0];
    };
@endphp
<section id="recenzii" class="mt-12 scroll-mt-32 border-t border-zinc-200 pt-8" aria-labelledby="h-recenzii">
    <h2 id="h-recenzii" class="text-xl font-bold tracking-tight">Recenzii</h2>

    <div class="mt-5 grid gap-8 lg:grid-cols-3">
        <div class="space-y-6">
            @if ($count > 0)
                <div>
                    <p class="text-4xl font-bold">{{ number_format($average, 1, ',', '') }}<span class="text-lg font-medium text-zinc-400"> / 5</span></p>
                    <x-rating-stars :rating="$average" size="size-5" class="mt-1" />
                    <p class="mt-1 text-sm text-zinc-500">{{ $count }} {{ $count === 1 ? 'recenzie' : 'recenzii' }}</p>
                </div>

                <dl class="space-y-1.5 text-sm">
                    @foreach ($distribution as $stars => $total)
                        <div class="flex items-center gap-2">
                            <dt class="w-10 shrink-0 text-zinc-600">{{ $stars }} ★</dt>
                            <dd class="flex flex-1 items-center gap-2">
                                <progress value="{{ $total }}" max="{{ max($count, 1) }}" aria-label="{{ $total }} recenzii cu {{ $stars }} stele"
                                          class="h-2 flex-1 appearance-none overflow-hidden rounded-full [&::-moz-progress-bar]:bg-amber-400 [&::-webkit-progress-bar]:bg-zinc-100 [&::-webkit-progress-value]:bg-amber-400"></progress>
                                <span class="w-6 text-right text-zinc-500">{{ $total }}</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="text-sm text-zinc-500">Acest produs nu are încă recenzii. Fii primul care îl evaluează.</p>
            @endif
        </div>

        <div class="space-y-6 lg:col-span-2">
            @auth
                <details class="group rounded-2xl border border-zinc-200 bg-zinc-50 p-4" @if ($errors->any() || $myReview) open @endif>
                    <summary class="cursor-pointer list-none text-sm font-semibold text-brand-700 [&::-webkit-details-marker]:hidden">
                        {{ $myReview ? 'Modifică recenzia ta' : 'Scrie o recenzie' }}
                    </summary>

                    @if ($myReview && $myReview->status === \App\Models\Review::PENDING)
                        <p class="mt-3 rounded-lg bg-amber-50 px-3 py-2 text-sm text-amber-900">Recenzia ta așteaptă aprobarea.</p>
                    @elseif ($myReview && $myReview->status === \App\Models\Review::REJECTED)
                        <p class="mt-3 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-800">Recenzia ta nu a fost aprobată. O poți modifica și retrimite.</p>
                    @endif

                    <form method="POST" action="{{ route('reviews.store', $product) }}" class="mt-4 space-y-4" novalidate>
                        @csrf
                        <fieldset>
                            <legend class="mb-1 text-sm font-medium">Nota ta</legend>
                            <div class="flex flex-wrap gap-2">
                                @foreach ([5, 4, 3, 2, 1] as $stars)
                                    <label class="flex cursor-pointer items-center gap-1.5 rounded-lg border border-zinc-300 bg-white px-3 py-1.5 text-sm has-[:checked]:border-brand-600 has-[:checked]:bg-brand-50 has-[:checked]:ring-1 has-[:checked]:ring-brand-600">
                                        <input type="radio" name="rating" value="{{ $stars }}" class="size-4 accent-brand-600" @checked((int) old('rating', $myReview?->rating) === $stars)>
                                        {{ $stars }} ★
                                    </label>
                                @endforeach
                            </div>
                            @error('rating')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </fieldset>

                        <x-field name="title" label="Titlu (opțional)" :value="$myReview?->title" maxlength="100" />

                        <div>
                            <label for="body" class="mb-1 block text-sm font-medium">Recenzia ta</label>
                            <textarea id="body" name="body" rows="4" maxlength="2000" required
                                      class="w-full rounded-lg border bg-white px-3 py-2 text-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/30 {{ $errors->has('body') ? 'border-red-500' : 'border-zinc-300' }}">{{ old('body', $myReview?->body) }}</textarea>
                            @error('body')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                        </div>

                        <x-button>Trimite recenzia</x-button>
                    </form>
                </details>
            @else
                <p class="rounded-2xl border border-zinc-200 bg-zinc-50 p-4 text-sm">
                    <a href="{{ route('login') }}" class="font-semibold text-brand-700 hover:underline">Autentifică-te</a> ca să scrii o recenzie.
                </p>
            @endauth

            @if ($reviews->isNotEmpty())
                <ul class="divide-y divide-zinc-200">
                    @foreach ($reviews as $review)
                        <li class="py-5 first:pt-0">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <x-rating-stars :rating="$review->rating" />
                                <span class="text-sm font-medium">{{ $author($review) }}</span>
                                @if ($review->verified_purchase)
                                    <span class="inline-flex items-center gap-1 rounded-full bg-green-50 px-2 py-0.5 text-xs font-medium text-green-700"><x-ui-icon name="check" class="size-3.5" /> Cumpărător verificat</span>
                                @endif
                                <time datetime="{{ $review->created_at?->toDateString() }}" class="text-xs text-zinc-400">{{ $review->created_at?->format('d.m.Y') }}</time>
                            </div>
                            @if ($review->title)
                                <h3 class="mt-2 font-semibold">{{ $review->title }}</h3>
                            @endif
                            <p class="mt-1 whitespace-pre-line text-sm leading-relaxed text-zinc-700">{{ $review->body }}</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</section>
