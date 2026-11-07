<?php

use App\Models\RestaurantTable;
use Livewire\Attributes\Title;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;

new #[Title('Table QR Codes')] class extends Component {
    public function boot(): void
    {
        auth()->user()?->unsetRelation('staffRole');
        Gate::authorize('manage-tables');
    }

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

<div class="w-full min-w-0 space-y-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between print-hidden">
        <x-page-heading title="Table QR Codes" description="Print QR codes for each table so customers can access the ordering menu." class="min-w-0 max-w-2xl" />
        <button type="button" onclick="printAll()" class="inline-flex min-h-11 w-full shrink-0 items-center justify-center rounded-lg bg-gray-900 px-5 py-2.5 text-sm font-medium text-white hover:bg-gray-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-indigo-600 sm:ml-auto sm:w-auto print-hidden">Print All</button>
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
                $url = route('customer-order.create', $table);
                $svg = $writer->writeString($url);
            @endphp
            <x-card class="qr-card" id="qr-card-{{ $table->id }}">
                <div class="text-center">
                    <p class="font-semibold text-gray-900 mb-1">{{ $table->name }}</p>
                    <p class="text-sm text-gray-500 mb-2">Scan to order</p>
                    <div class="flex justify-center mb-2">{!! $svg !!}</div>
                    <p class="text-sm text-gray-400 break-all">{{ $url }}</p>
                    <button onclick="printOne({{ $table->id }})" class="row-action mt-3 print-hidden">Print</button>
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
