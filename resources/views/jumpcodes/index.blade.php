<!DOCTYPE html>
<html lang="hu" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ugrókód generátor</title>
    @vite('resources/css/app.css')
</head>
<body class="h-full bg-gray-50 dark:bg-gray-900 text-gray-800 dark:text-gray-100 flex items-center justify-center">

    {{-- Középre igazított kártya --}}
    <div class="w-full max-w-md sm:max-w-lg md:max-w-2xl lg:max-w-3xl p-6 bg-white dark:bg-gray-800/60 rounded-2xl shadow-xl">
        
        {{-- Kártya fejléce --}}
        <div class="flex items-center gap-3 mb-4">
            <x-heroicon-o-key class="icon-6 text-indigo-600 dark:text-indigo-400"/>
            <h2 class="text-2xl font-semibold tracking-tight">Ugrókód generátor :</h2>
        </div>

        {{-- Form --}}
        @include('jumpcodes._form')

    </div>

    @vite('resources/js/app.js')
</body>
</html>
