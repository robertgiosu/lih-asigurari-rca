@props([
    'name',
    'label',
    'options' => [],
    'value' => null,
    'required' => false,
    'placeholder' => null,
    'hint' => null,
])

@php
    $htmlName = preg_replace('/\.([^.]+)/', '[$1]', $name);
    $id = str_replace('.', '-', $name);
    $error = $errors->first($name);

    // Prioritatea: ce a completat utilizatorul acum > datele din contul lui > valoarea implicita.
    // $prefill e pus la dispozitie de controller cu view()->share(); pe paginile
    // fara profil pur si simplu nu exista.
    $selected = old($name, ($prefill ?? [])[$name] ?? $value);
@endphp

<div>
    <label for="{{ $id }}" class="block text-sm font-medium text-slate-700">
        {{ $label }}@if($required)<span class="text-rose-500"> *</span>@endif
    </label>

    <select
        id="{{ $id }}"
        name="{{ $htmlName }}"
        {{ $attributes->class([
            'mt-1 block w-full rounded-md border bg-white px-3 py-2 text-sm shadow-sm outline-none focus:ring-2',
            'border-slate-300 focus:border-sky-500 focus:ring-sky-100' => ! $error,
            'border-rose-400 bg-rose-50 focus:border-rose-500 focus:ring-rose-100' => $error,
        ]) }}
    >
        @if($placeholder)
            <option value="">{{ $placeholder }}</option>
        @endif

        @foreach($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $selected === (string) $optionValue)>
                {{ $optionLabel }}
            </option>
        @endforeach
    </select>

    @if($error)
        <p class="mt-1 text-xs text-rose-600">{{ $error }}</p>
    @elseif($hint)
        <p class="mt-1 text-xs text-slate-500">{{ $hint }}</p>
    @endif
</div>
