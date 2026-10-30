@props(['name', 'label', 'type' => 'text', 'value' => null, 'required' => false, 'autocomplete' => null, 'hint' => null])
<div>
    <label for="{{ $name }}" class="mb-1 block text-sm font-medium">
        {{ $label }}@if ($required)<span class="text-red-600" aria-hidden="true"> *</span>@endif
    </label>
    <input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}"
           value="{{ $type === 'password' ? '' : old($name, $value) }}"
           @if ($required) required @endif
           @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif
           @if ($errors->has($name)) aria-invalid="true" aria-describedby="{{ $name }}-error" @endif
           {{ $attributes->class(['w-full rounded-lg border px-3 py-2 text-sm', $errors->has($name) ? 'border-red-500' : 'border-zinc-300']) }}>
    @if ($hint)<p class="mt-1 text-xs text-zinc-500">{{ $hint }}</p>@endif
    @error($name)<p id="{{ $name }}-error" class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
</div>
