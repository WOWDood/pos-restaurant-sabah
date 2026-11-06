@if (session()->has('action_success'))
    <p role="status" class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">{{ session('action_success') }}</p>
@endif
<p wire:offline role="status" class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">Connection lost. Reconnect before saving changes or taking payment.</p>
