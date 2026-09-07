<!DOCTYPE html>
  <html lang="ro" class="h-full">
  <head>
      <meta charset="utf-8">
      <meta name="viewport" content="width=device-width,
  initial-scale=1">
      <title>@yield('title', 'Calculator RCA')</title>
@fonts
@vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-full bg-slate-100 text-slate-900 antialiased">

<header class="border-b border-slate-200 bg-white">
    <div class="mx-auto flex max-w-6xl items-center justify-between
  px-4 py-4">
        <a href="{{ route('oferta.create') }}" class="text-lg
  font-semibold tracking-tight">
            Calculator <span class="text-sky-600">RCA</span>
        </a>
        <nav class="text-sm text-slate-600">
            <a href="{{ route('oferta.create') }}"
               class="hover:text-sky-600">Ofertă nouă</a>
        </nav>
    </div>
</header>

<main class="mx-auto max-w-6xl px-4 py-8">
    @yield('content')
</main>

</body>
</html>
