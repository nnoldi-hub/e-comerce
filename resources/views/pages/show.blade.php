<x-layouts.app :title="$page->title" :description="$page->meta_description">
    <article class="mx-auto max-w-3xl">
        <h1 class="text-3xl font-semibold tracking-tight">{{ $page->title }}</h1>

        <div class="mt-6 leading-relaxed text-zinc-800
                    [&_h2]:mt-8 [&_h2]:text-xl [&_h2]:font-semibold
                    [&_h3]:mt-6 [&_h3]:text-lg [&_h3]:font-semibold
                    [&_p]:mt-3 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-6 [&_ol]:mt-3 [&_ol]:list-decimal [&_ol]:pl-6
                    [&_li]:mt-1 [&_a]:text-brand-600 [&_a]:underline [&_strong]:font-semibold
                    [&_blockquote]:mt-3 [&_blockquote]:border-l-4 [&_blockquote]:border-zinc-200 [&_blockquote]:pl-4 [&_blockquote]:text-zinc-600">
            {!! $page->html() !!}
        </div>

        <p class="mt-10 text-sm text-zinc-500">Ultima actualizare: {{ $page->updated_at?->format('d.m.Y') }}</p>
    </article>
</x-layouts.app>
