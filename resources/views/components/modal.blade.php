@props(['show' => false, 'title' => null, 'close' => null, 'wide' => false])

@if ($show)
    <div class="fixed inset-0 bg-black/40 flex items-center justify-center z-[60]"
         @if ($close) wire:keydown.escape="{{ $close }}" @endif>
        <div class="bg-white rounded-lg shadow-lg w-full {{ $wide ? 'max-w-3xl' : 'max-w-md' }} max-h-[90vh] flex flex-col">
            @if ($title)
                <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200 sticky top-0 bg-white">
                    <h2 class="text-lg font-semibold">{{ $title }}</h2>
                    @if ($close)
                        <button type="button" wire:click="{{ $close }}" class="text-gray-400 hover:text-gray-600 text-xl leading-none">&times;</button>
                    @endif
                </div>
            @endif
            <div class="overflow-y-auto p-6">
                {{ $slot }}
            </div>
        </div>
    </div>
@endif