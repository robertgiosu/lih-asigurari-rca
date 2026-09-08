@props(['name', 'label', 'checked' => false])

@php
    $htmlName = preg_replace('/\.([^.]+)/', '[$1]', $name);
    $id = str_replace('.', '-', $name);

    // La prima afisare folosim datele din cont, apoi valoarea implicita;
    // dupa o eroare de validare, un checkbox nebifat lipseste din datele trimise,
    // deci session()->hasOldInput() e singurul mod de a distinge cele doua cazuri.
    $isChecked = session()->hasOldInput()
        ? (bool) old($name)
        : (bool) (($prefill ?? [])[$name] ?? $checked);
@endphp

<label for="{{ $id }}" class="flex items-center gap-2 text-sm text-slate-700">
    <input
        type="checkbox"
        id="{{ $id }}"
        name="{{ $htmlName }}"
        value="1"
        @checked($isChecked)
        {{ $attributes->class('h-4 w-4 rounded border-slate-300 text-sky-600 focus:ring-sky-200') }}
    >
    {{ $label }}
</label>
