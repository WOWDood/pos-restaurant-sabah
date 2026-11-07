@props(['order'])
@if ((float) $order->tax_rate > 0)
    <div class="flex justify-between gap-3"><span>Subtotal</span><span>RM{{ number_format((float) $order->subtotal, 2) }}</span></div>
    <div class="flex justify-between gap-3"><span>{{ $order->tax_name ?: 'Tax' }} ({{ number_format((float) $order->tax_rate, 2) }}%)</span><span>RM{{ number_format((float) $order->tax_amount, 2) }}</span></div>
@endif
