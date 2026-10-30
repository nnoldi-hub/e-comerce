@foreach ($categoriesByParent[$parentId] ?? [] as $node)
    @php($open = $currentPath !== null && ($currentPath === $node->path || str_starts_with($currentPath, $node->path.'/')))
    <li>
        <a href="{{ route('catalog.category', ['path' => $node->path]) }}"
           @if ($currentPath === $node->path) aria-current="page" @endif
           class="block rounded px-2 py-1 text-sm hover:bg-zinc-100 {{ $currentPath === $node->path ? 'bg-brand-50 font-medium text-brand-700' : '' }}">{{ $node->name }}</a>
        @if ($open && isset($categoriesByParent[$node->id]))
            <ul class="ml-3 mt-1 space-y-1 border-l border-zinc-200 pl-2">
                @include('catalog.partials.category-tree', ['parentId' => $node->id, 'categoriesByParent' => $categoriesByParent, 'currentPath' => $currentPath])
            </ul>
        @endif
    </li>
@endforeach
