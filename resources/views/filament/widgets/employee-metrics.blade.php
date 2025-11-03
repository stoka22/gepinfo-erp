{{-- resources/views/filament/widgets/employee-metrics.blade.php --}}
<x-filament::section>
  <div class="grid gap-4 md:grid-cols-5">
    {{-- Keret (2025) --}}
    <div class="rounded-2xl border bg-card shadow-sm p-4">
      <div class="text-sm font-medium text-muted-foreground">Keret ({{ now()->year }})</div>
      <div class="mt-2 text-3xl font-semibold tracking-tight">{{ number_format($this->quotaYear, 1, ',', ' ') }}</div>
    </div>

    {{-- Felhasznált --}}
    <div class="rounded-2xl border bg-card shadow-sm p-4">
      <div class="text-sm font-medium text-muted-foreground">Felhasznált</div>
      <div class="mt-2 text-3xl font-semibold tracking-tight">{{ number_format($this->usedYear, 1, ',', ' ') }}</div>
    </div>

    {{-- Kivehető --}}
    <div class="rounded-2xl border bg-card shadow-sm p-4">
      <div class="text-sm font-medium text-muted-foreground">Kivehető</div>
      <div class="mt-2 text-3xl font-semibold tracking-tight">{{ number_format($this->available, 1, ',', ' ') }}</div>
    </div>

    {{-- Összes éves (Túlóra) --}}
    <div class="rounded-2xl border bg-card shadow-sm p-4">
      <div class="text-sm font-medium text-muted-foreground">Túlóra – összes ({{ now()->year }})</div>
      <div class="mt-2 text-3xl font-semibold tracking-tight">{{ number_format($this->overtimeYear, 1, ',', ' ') }}</div>
    </div>

    {{-- Aktuális havi (Túlóra) --}}
    <div class="rounded-2xl border bg-card shadow-sm p-4">
      <div class="text-sm font-medium text-muted-foreground">Túlóra – aktuális havi</div>
      <div class="mt-2 text-3xl font-semibold tracking-tight">{{ number_format($this->overtimeMonth, 1, ',', ' ') }}</div>
    </div>
  </div>
</x-filament::section>
