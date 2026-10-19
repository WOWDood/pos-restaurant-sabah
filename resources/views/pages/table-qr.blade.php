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

<style>
    @media print {
        body { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
        .qr-grid { grid-template-columns: repeat(2, 1fr) !important; }
        .qr-card { break-inside: avoid; page-break-inside: avoid; }
        .print-hidden { display: none !important; }
        .printing-single .qr-card:not(.print-single) { display: none !important; }
        .printing-single .qr-card.print-single { margin: 0 auto; }
    }
</style>

<div class="space-y-4">
    <div class="flex items-center justify-between print-hidden">
        <div>
            <h1 class="text-xl font-bold text-gray-900">Table QR Codes</h1>
            <p class="text-sm text-gray-500 mt-1">Print these QR codes and stick them on each table. Customers scan to open the ordering menu.</p>
        </div>
        <button onclick="printAll()" class="bg-gray-900 text-white text-sm font-medium px-4 py-2 rounded print-hidden">Print All</button>
    </div>

    @php
        $renderer = new \BaconQrCode\Renderer\ImageRenderer(
            new \BaconQrCode\Renderer\RendererStyle\RendererStyle(120),
            new \BaconQrCode\Renderer\Image\SvgImageBackEnd()
        );
        $writer = new \BaconQrCode\Writer($renderer);
    @endphp

    <div id="qr-container" class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 qr-grid">
        @foreach ($this->tables as $table)
            @php
                $url = url('/order/' . $table->id);
                $svg = $writer->writeString($url);
            @endphp
            <x-card class="qr-card" id="qr-card-{{ $table->id }}">
                <div class="text-center">
                    <p class="font-semibold text-gray-900 mb-1">{{ $table->name }}</p>
                    <p class="text-xs text-gray-500 mb-2">Scan to order</p>
                    <div class="flex justify-center mb-2">{!! $svg !!}</div>
                    <p class="text-xs text-gray-400 break-all">{{ $url }}</p>
                    <button onclick="printOne({{ $table->id }})" class="mt-2 text-xs text-indigo-600 hover:underline print-hidden">Print</button>
                </div>
            </x-card>
        @endforeach
    </div>

    <script>
        function printOne(id) {
            const container = document.getElementById('qr-container');
            const card = document.getElementById('qr-card-' + id);
            container.classList.add('printing-single');
            card.classList.add('print-single');
            window.print();
        }
        window.addEventListener('afterprint', function () {
            const container = document.getElementById('qr-container');
            container.classList.remove('printing-single');
            document.querySelectorAll('.qr-card.print-single').forEach(function (el) {
                el.classList.remove('print-single');
            });
        });
        function printAll() {
            const container = document.getElementById('qr-container');
            container.classList.remove('printing-single');
            document.querySelectorAll('.qr-card.print-single').forEach(function (el) {
                el.classList.remove('print-single');
            });
            window.print();
        }
    </script>
</div>