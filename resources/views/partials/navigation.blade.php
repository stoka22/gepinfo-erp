<nav class="bg-white border-b mb-4">
  <div class="max-w-7xl mx-auto px-4 py-3 flex items-center justify-between">
    <div class="flex items-center gap-4">
      <a href="{{ url('/') }}" class="text-lg font-semibold">Gepinfo</a>
      <a href="{{ route('jumpcodes.public') }}" class="text-sm text-gray-700 hover:text-gray-900">Jump-kód generátor</a>
    </div>

    <div class="flex items-center gap-3">
      @auth
        <span class="text-sm text-gray-600">Bejelentkezve: {{ Auth::user()->name }}</span>
        {{-- Nincs egységes 'logout' route -- a Filament admin és user panel külön-külön
             regisztrálja a sajátját (filament.admin.auth.logout / filament.user.auth.logout). --}}
        <form method="POST" action="{{ route((Auth::user()->role ?? null) === 'admin' ? 'filament.admin.auth.logout' : 'filament.user.auth.logout') }}">
          @csrf
          <button class="text-sm px-3 py-1 rounded bg-red-500 text-white">Kijelentkezés</button>
        </form>
      @else
        <a href="{{ route('login') }}" class="text-sm text-primary-600">Belépés</a>
      @endauth
    </div>
  </div>
</nav>
