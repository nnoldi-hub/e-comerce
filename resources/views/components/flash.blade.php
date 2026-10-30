@php
    $messages = [
        'status' => 'bg-green-50 text-green-800 border-green-200',
        'payment_notice' => 'bg-green-50 text-green-800 border-green-200',
        'error' => 'bg-red-50 text-red-800 border-red-200',
        'payment_error' => 'bg-red-50 text-red-800 border-red-200',
    ];
@endphp
@foreach ($messages as $key => $classes)
    @if (session($key))
        <div role="{{ str_contains($key, 'error') || $key === 'error' ? 'alert' : 'status' }}" class="mb-4 rounded-lg border px-4 py-3 text-sm {{ $classes }}">{{ session($key) }}</div>
    @endif
@endforeach
