{{-- resources/views/jumpcodes/index.blade.php --}}
<!doctype html>
<html lang="hu">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Ugrókód generátor</title>
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <link rel="stylesheet" href="{{ asset('css/app.css') }}">
</head>
<body class="bg-gray-50 text-gray-800">
  <div class="min-h-screen flex items-start justify-center py-10">
    @include('jumpcodes._form')
  </div>
  <script src="{{ asset('js/app.js') }}"></script>
</body>
</html>
