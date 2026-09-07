@props(['title', 'description' => null])

<section class="rounded-xl border border-slate-200 bg-white p-6
  shadow-sm">
    <header class="mb-5 border-b border-slate-100 pb-3">
        <h2 class="text-base font-semibold text-slate-900">{{ $title
  }}</h2>
        @if($description)
            <p class="mt-1 text-sm text-slate-500">{{ $description
  }}</p>
        @endif
    </header>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {{ $slot }}
    </div>
</section>
