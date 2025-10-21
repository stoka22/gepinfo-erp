<!doctype html>
<html lang="hu" class="h-full">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Ugrókód generátor</title>

  {{-- a projekt CSS-e (Vite vagy asset – amelyiket használod) --}}
  {{-- @vite(['resources/css/app.css']) --}}
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">

  @livewireStyles
</head>
<body class="h-full bg-gray-50 text-gray-800 dark:bg-gray-900 dark:text-gray-100">
  <main class="min-h-full py-10 px-4 sm:px-6">
    <livewire:public-jump-code-generator />
  </main>

  {{-- projekt JS --}}
  {{-- @vite(['resources/js/app.js']) --}}
  <script src="{{ asset('js/app.js') }}"></script>

  @livewireScripts
</body>
</html>
