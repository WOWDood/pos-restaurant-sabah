@props(['show' => false, 'title' => null, 'close' => null])

@if ($show)
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-50"
         @if ($close) wire:keydown.escape="{{ $close }}" @endif>
        <div class="bg-white rounded-lg shadow-lg p-6 w-full max-w-md">
            @if ($title)
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-lg font-semibold">{{ $title }}</h2>
                    @if ($close)
                        <button type="button" wire:click="{{ $close }}" class="text-gray-400 hover:text-gray-600">&times;</button>
                    @endif
                </div>
            @endif
            {{ $slot }}
        </div>
    </div>
@endif