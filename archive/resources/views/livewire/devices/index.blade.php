<?php

use App\Models\Device;

$devices = auth()->user()->role==='admin'
    ? Device::latest()->paginate(15)
    : Device::where('user_id', auth()->id())->latest()->paginate(15);

?>

<div class="p-6">
  <div class="flex justify-between mb-4">
    <h1 class="text-2xl font-bold">Eszközök</h1>
    <a href="{{ route('devices.create') }}" class="btn btn-primary">Új eszköz</a>
  </div>

  <table class="w-full text-sm">
    <thead><tr>
      <th class="text-left p-2">Név</th>
      <th class="text-left p-2">MAC</th>
      <th class="text-left p-2">Token</th>
      <th class="text-left p-2">Tulaj</th>
      <th class="p-2"></th>
    </tr></thead>
    <tbody>
      @foreach($devices as $d)
      <tr class="border-b">
        <td class="p-2">{{ $d->name }}</td>
        <td class="p-2">{{ $d->mac_address }}</td>
        <td class="p-2 font-mono truncate max-w-[220px]">{{ $d->device_token }}</td>
        <td class="p-2">{{ $d->user->name }}</td>
        <td class="p-2">
          <a href="{{ route('devices.edit',$d) }}" class="text-blue-600">Szerk.</a>
        </td>
      </tr>
      @endforeach
    </tbody>
  </table>

  <div class="mt-4">{{ $devices->links() }}</div>
</div>
