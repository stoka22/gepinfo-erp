<!doctype html>
<html lang="hu" class="h-full">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ugrókód generátor</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  {{-- a projekt Tailwindje --}}
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="h-full bg-gray-50 text-gray-800 dark:bg-gray-900 dark:text-gray-100">
  <div class="min-h-full py-10">
    <div class="mx-auto max-w-3xl px-4 sm:px-6">
      @include('jumpcodes._form')
    </div>
  </div>
  <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
