@props(['title', 'description' => null])

<header {{ $attributes->class('space-y-1') }}>
    <h1 class="text-2xl font-bold tracking-tight text-gray-900">{{ $title }}</h1>
    @if ($description)
        <p class="text-base leading-relaxed text-gray-600">{{ $description }}</p>
    @endif
</header>
