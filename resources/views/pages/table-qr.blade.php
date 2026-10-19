<?php

use App\Models\RestaurantTable;
use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Table QR Codes')] class extends Component {
    public function getTablesProperty()
    {
        return RestaurantTable::orderBy('name')->get();
    }
}; ?>

<div class="space-y-4">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Table QR Codes</h1>
            <p class="text-sm text-gray-500 mt-1">Print these QR codes and stick them on each table. Customers scan to open the ordering menu.</p>
        </div>
        <button onclick="window.print()" class="bg-gray-900 text-white text-sm font-medium px-4 py-2 rounded">Print</button>
    </div>

    @php
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(120),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $writer = new \BaconQrCode\Writer($renderer);
    @endphp

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
        @foreach ($this->tables as $table)
            @php
                $url = url('/order/' . $table->id);
                $svg = $writer->writeString($url);
            @endphp
            <x-card>
                <div class="text-center">
                    <p class="font-semibold text-gray-900 mb-1">{{ $table->name }}</p>
                    <p class="text-xs text-gray-500 mb-2">Scan to order</p>
                    <div class="flex justify-center mb-2">{!! $svg !!}</div>
                    <p class="text-xs text-gray-400 break-all">{{ $url }}</p>
                </div>
            </x-card>
        @endforeach
    </div>
</div>