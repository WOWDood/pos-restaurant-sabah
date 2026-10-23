<div class="overflow-x-auto">
    <table {{ $attributes->merge(['class' => 'w-full min-w-[640px] text-sm [&>tbody>tr:hover]:bg-gray-50 [&>tbody>tr:nth-child(even)]:bg-gray-50/50']) }}>
        {{ $slot }}
    </table>
</div>