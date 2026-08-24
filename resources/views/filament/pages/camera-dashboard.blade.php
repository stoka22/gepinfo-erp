<x-filament::page>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse ($cameras as $camera)
            <div class="rounded-lg overflow-hidden bg-black">
                <div class="px-3 py-2 text-sm text-white bg-gray-800">{{ $camera['name'] }}</div>
                <video
                    class="camera-video w-full"
                    data-src="{{ $camera['stream_url'] }}"
                    controls
                    autoplay
                    muted
                    playsinline
                ></video>
            </div>
        @empty
            <p>Nincs beállított kamera ehhez a telephelyhez.</p>
        @endforelse
    </div>

    @vite('resources/js/camera-dashboard.js')
</x-filament::page>
