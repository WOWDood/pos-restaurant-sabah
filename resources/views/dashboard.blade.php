<x-layouts::app :title="__('Dashboard')">
    <div class="max-w-xl">
        <x-card>
            <h1 class="text-xl font-bold text-gray-900 mb-4">Dashboard</h1>
            <div class="space-y-1">
                <p class="text-lg font-semibold text-gray-900">{{ $settings['business_name'] ?? 'Kedai Soto Banso' }}</p>
                <p class="text-sm text-gray-500">{{ $settings['location'] ?? 'Tuaran' }}</p>
                <p class="text-sm text-gray-500">{{ $settings['phone'] ?? '012-8261800' }}</p>
            </div>
        </x-card>
    </div>
</x-layouts::app>