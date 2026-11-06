<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-gray-100 text-sm text-gray-900" x-data="{ sidebarOpen: false }" @keydown.escape.window="sidebarOpen = false">
        {{-- Top header bar --}}
        <header class="fixed top-0 left-0 right-0 h-12 bg-indigo-600 flex items-center px-4 text-white print:hidden z-50">
            <button type="button" class="pos-menu-toggle mr-3 md:hidden" @click="sidebarOpen = !sidebarOpen" aria-label="Toggle navigation" aria-controls="pos-sidebar" :aria-expanded="sidebarOpen">
                <flux:icon.bars-3 class="w-5 h-5" />
            </button>
            <a href="{{ route('home') }}" class="pos-header-logo" aria-label="POS System XE home" wire:navigate><x-system-logo /></a>
        </header>

        <div class="flex min-h-screen print:block">
            <div x-cloak x-show="sidebarOpen" x-transition.opacity.duration.200ms class="pos-sidebar-backdrop fixed inset-0 top-12 print:hidden z-30 md:hidden" @click="sidebarOpen = false" aria-hidden="true"></div>

            {{-- Left sidebar --}}
            <aside
                id="pos-sidebar"
                class="pos-sidebar fixed top-12 left-0 bottom-0 w-60 flex flex-col print:hidden z-40"
                :class="{ 'is-open': sidebarOpen }"
                aria-label="Restaurant workspace"
            >
                <div class="pos-sidebar-heading">
                    <span class="pos-sidebar-name">Kedai Soto Banso</span>
                    <a href="{{ route('home') }}" class="pos-sidebar-home" aria-label="{{ __('Website home') }}" title="{{ __('Website home') }}" wire:navigate>
                        <flux:icon.home class="size-4" aria-hidden="true" />
                    </a>
                </div>

                <nav class="pos-sidebar-nav" aria-label="Main navigation">
                    @foreach ([
                        ['Dashboard', route('dashboard'), request()->routeIs('dashboard')],
                        ['Orders', route('orders'), request()->routeIs('orders')],
                        ['POS', route('pos'), request()->routeIs('pos')],
                    ] as [$label, $href, $active])
                        <a href="{{ $href }}" class="pos-sidebar-link {{ $active ? 'is-active' : '' }}" @if($active) aria-current="page" @endif @if($href !== '#' && $label !== 'Orders') wire:navigate @endif>
                            @if ($label === 'Orders')
                                <flux:icon.document-text class="pos-sidebar-icon" aria-hidden="true" />
                            @elseif ($label === 'POS')
                                <flux:icon.shopping-bag class="pos-sidebar-icon" aria-hidden="true" />
                            @else
                                <flux:icon.squares-2x2 class="pos-sidebar-icon" aria-hidden="true" />
                            @endif
                            {{ __($label) }}
                        </a>
                    @endforeach

                    {{-- Setup (expandable) --}}
                    <details class="pos-sidebar-group" open>
                        <summary class="pos-sidebar-link">
                            <flux:icon.cog-6-tooth class="pos-sidebar-icon" aria-hidden="true" />
                            {{ __('Setup') }}
                            <flux:icon.chevron-right class="pos-sidebar-chevron" aria-hidden="true" />
                        </summary>
                        <div class="pos-sidebar-submenu">
                            <a href="{{ route('categories') }}" class="pos-sidebar-link pos-sidebar-sublink {{ request()->routeIs('categories') ? 'is-active' : '' }}" @if(request()->routeIs('categories')) aria-current="page" @endif wire:navigate>
                                {{ __('Category') }}
                            </a>
                            <a href="{{ route('items') }}" class="pos-sidebar-link pos-sidebar-sublink {{ request()->routeIs('items') ? 'is-active' : '' }}" @if(request()->routeIs('items')) aria-current="page" @endif wire:navigate>
                                {{ __('Item') }}
                            </a>
                            <a href="{{ route('tables') }}" class="pos-sidebar-link pos-sidebar-sublink {{ request()->routeIs('tables') ? 'is-active' : '' }}" @if(request()->routeIs('tables')) aria-current="page" @endif wire:navigate>
                                {{ __('Table') }}
                            </a>
                            <a href="{{ route('table-qr') }}" class="pos-sidebar-link pos-sidebar-sublink {{ request()->routeIs('table-qr') ? 'is-active' : '' }}" @if(request()->routeIs('table-qr')) aria-current="page" @endif wire:navigate>
                                {{ __('Table QR') }}
                            </a>
                            <a href="{{ route('payment-methods') }}" class="pos-sidebar-link pos-sidebar-sublink {{ request()->routeIs('payment-methods') ? 'is-active' : '' }}" @if(request()->routeIs('payment-methods')) aria-current="page" @endif wire:navigate>
                                {{ __('Payment Method') }}
                            </a>
                            @can('manage-users')
                                <a href="{{ route('admin.users') }}" class="pos-sidebar-link pos-sidebar-sublink {{ request()->routeIs('admin.users') ? 'is-active' : '' }}" @if(request()->routeIs('admin.users')) aria-current="page" @endif wire:navigate>
                                    {{ __('Manage Users') }}
                                </a>
                            @endcan
                        </div>
                    </details>
                </nav>

                <div class="pos-sidebar-footer">
                    <details class="pos-sidebar-group" open>
                        <summary class="pos-sidebar-link pos-sidebar-account">
                            <span class="pos-sidebar-avatar"><flux:icon.user class="pos-sidebar-icon" aria-hidden="true" /></span>
                            <span class="pos-sidebar-username">{{ auth()->user()->name }}</span>
                            <flux:icon.chevron-right class="pos-sidebar-chevron" aria-hidden="true" />
                        </summary>
                        <div class="pos-sidebar-submenu">
                            <a href="{{ route('profile') }}" class="pos-sidebar-link pos-sidebar-sublink {{ request()->routeIs('profile') ? 'is-active' : '' }}" @if(request()->routeIs('profile')) aria-current="page" @endif wire:navigate>
                                {{ __('Profile') }}
                            </a>
                            <form method="POST" action="{{ route('logout') }}" class="w-full">
                                @csrf
                                <button type="submit" class="pos-sidebar-link pos-sidebar-sublink pos-sidebar-logout">
                                    {{ __('Log Out') }}
                                </button>
                            </form>
                        </div>
                    </details>
                </div>
            </aside>

            {{-- Main content --}}
            <main class="ml-0 md:ml-60 mt-12 p-4 sm:p-6 bg-gray-100 min-h-screen print:ml-0 print:mt-0">
                <div class="max-w-6xl mx-auto min-w-0">
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
