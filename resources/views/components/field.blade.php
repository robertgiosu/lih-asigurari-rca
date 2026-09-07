@props([
    'name',
    'label',
    'type' => 'text',
    'value' => null,
    'required' => false,
    'hint' => null,
])

@php
    // Validarea foloseste 'policyholder.address.floor', dar HTML-ul are nevoie
    // de 'policyholder[address][floor]' ca sa produca un array imbricat.
    $htmlName = preg_replace('/\.([^.]+)/', '[$1]', $name);
    $id = str_replace('.', '-', $name);
    $error = $errors->first($name);
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-medium
  text-slate-700">
        {{ $label }}@if($required)<span class="text-rose-500">
  *</span>@endif
    </label>

    <input
        type="{{ $type }}"
        id="{{ $id }}"
        name="{{ $htmlName }}"
        value="{{ old($name, $value) }}"
        @if($required) required @endif
        {{ $attributes->class([
            'mt-1 block w-full rounded-md border px-3 py-2 text-sm
shadow-sm outline-none focus:ring-2',
            'border-slate-300 focus:border-sky-500 focus:ring-sky-100'
=> ! $error,
            'border-rose-400 bg-rose-50 focus:border-rose-500
focus:ring-rose-100' => $error,
        ]) }}
    >

    @if($error)
        <p class="mt-1 text-xs text-rose-600">{{ $error }}</p>
    @elseif($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
