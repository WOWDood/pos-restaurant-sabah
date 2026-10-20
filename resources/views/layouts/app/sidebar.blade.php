<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gray-100 text-gray-900">
        {{-- Top header bar --}}
        <header class="fixed top-0 left-0 right-0 h-12 bg-indigo-600 flex items-center px-4 text-white print:hidden z-50">
            <span class="font-semibold text-sm">Livewire Shop</span>
        </header>

        <div class="flex min-h-screen print:block">
            {{-- Left sidebar --}}
            <aside class="fixed top-12 left-0 bottom-0 w-60 bg-indigo-700 text-white flex flex-col print:hidden z-40 overflow-y-auto">
                <div class="px-4 py-3 font-semibold text-sm border-b border-indigo-500">
                    Kedai Soto Banso
                </div>

                <nav class="flex-1 py-2 text-sm overflow-y-auto">
                    @php
                        $navItem = function ($label, $href, $active = false, $icon = null) {
                            $classes = 'flex items-center gap-2 px-4 py-2 hover:bg-indigo-600';
                            if ($active) {
                                $classes .= ' bg-indigo-500';
                            }
                            return [$classes, $href];
                        };
                    @endphp

                    @foreach ([
                        ['Welcome', route('home'), request()->routeIs('home')],
                        ['Dashboard', route('dashboard'), request()->routeIs('dashboard')],
                        ['Orders', route('orders'), request()->routeIs('orders')],
                        ['POS', route('pos'), request()->routeIs('pos')],
                        ['Test Drawing', '#', false],
                    ] as [$label, $href, $active])
                        <a href="{{ $href }}" class="flex items-center gap-2 px-4 py-2 hover:bg-indigo-600 {{ $active ? 'bg-indigo-500' : '' }}" @if($href !== '#' && $label !== 'Orders') wire:navigate @endif>
                            @if ($label === 'Orders')
                                <flux:icon.document-text class="w-4 h-4 text-white opacity-80" />
                            @elseif ($label === 'POS')
                                <flux:icon.shopping-bag class="w-4 h-4 text-white opacity-80" />
                            @elseif ($label === 'Test Drawing')
                                <flux:icon.pencil class="w-4 h-4 text-white opacity-80" />
                            @else
                                <flux:icon.home class="w-4 h-4 text-white opacity-80" />
                            @endif
                            {{ __($label) }}
                        </a>
                    @endforeach

                    {{-- Setup (expandable) --}}
                    <details class="group" open>
                        <summary class="flex items-center gap-2 px-4 py-2 hover:bg-indigo-600 cursor-pointer list-none">
                            <flux:icon.cog-6-tooth class="w-4 h-4 text-white opacity-80" />
                            {{ __('Setup') }}
                            <span class="ml-auto text-xs opacity-70 group-open:rotate-90 transition-transform">&#9654;</span>
                        </summary>
                        <div class="pl-8">
                            <a href="{{ route('categories') }}" class="flex items-center gap-2 px-4 py-1.5 hover:bg-indigo-600 text-indigo-100 {{ request()->routeIs('categories') ? 'bg-indigo-500' : '' }}" wire:navigate>
                                {{ __('Category') }}
                            </a>
                            <a href="{{ route('items') }}" class="flex items-center gap-2 px-4 py-1.5 hover:bg-indigo-600 text-indigo-100 {{ request()->routeIs('items') ? 'bg-indigo-500' : '' }}" wire:navigate>
                                {{ __('Item') }}
                            </a>
                            <a href="{{ route('tables') }}" class="flex items-center gap-2 px-4 py-1.5 hover:bg-indigo-600 text-indigo-100 {{ request()->routeIs('tables') ? 'bg-indigo-500' : '' }}" wire:navigate>
                                {{ __('Table') }}
                            </a>
                            <a href="{{ route('table-qr') }}" class="flex items-center gap-2 px-4 py-1.5 hover:bg-indigo-600 text-indigo-100 {{ request()->routeIs('table-qr') ? 'bg-indigo-500' : '' }}" wire:navigate>
                                {{ __('Table QR') }}
                            </a>
                            <a href="{{ route('payment-methods') }}" class="flex items-center gap-2 px-4 py-1.5 hover:bg-indigo-600 text-indigo-100 {{ request()->routeIs('payment-methods') ? 'bg-indigo-500' : '' }}" wire:navigate>
                                {{ __('Payment Method') }}
                            </a>
                        </div>
                    </details>
                </nav>

                <div class="border-t border-indigo-500 shrink-0">
                    <details class="group" open>
                        <summary class="flex items-center gap-2 px-4 py-2 hover:bg-indigo-600 cursor-pointer list-none">
                            <flux:icon.user class="w-4 h-4 text-white opacity-80" />
                            {{ auth()->user()->name }}
                            <span class="ml-auto text-xs opacity-70 group-open:rotate-90 transition-transform">&#9654;</span>
                        </summary>
                        <div class="pl-8">
                            <a href="{{ route('profile') }}" class="flex items-center gap-2 px-4 py-1.5 hover:bg-indigo-600 text-indigo-100 {{ request()->routeIs('profile') ? 'bg-indigo-500' : '' }}" wire:navigate>
                                {{ __('Profile') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <button type="submit" class="w-full text-left flex items-center gap-2 px-4 py-1.5 hover:bg-indigo-600 text-indigo-100">
                                    {{ __('Log Out') }}
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </aside>

            {{-- Main content --}}
            <main class="ml-60 mt-12 p-6 bg-gray-100 min-h-screen print:ml-0 print:mt-0">
                <div class="max-w-6xl mx-auto">
                    {{ $slot }}
                </div>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>