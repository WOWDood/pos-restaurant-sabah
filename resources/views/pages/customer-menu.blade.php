<?php

use App\Actions\PriceOrderItem;
use App\Models\Category;
use App\Models\Item;
use App\Models\RestaurantTable;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component {
    #[Locked]
    public int $tableId;
    #[Locked]
    public string $tableName;
    #[Locked]
    public string $businessName;
    #[Locked]
    public array $cart = [];
    public string $search = '';
    public ?int $selectedItemId = null;
    public array $selections = [];
    public int $quantity = 1;
    public string $notes = '';
    public bool $showItem = false;
    public bool $showCart = false;

    public function mount(RestaurantTable $table, string $businessName): void
    {
        $this->tableId = (int) $table->id;
        $this->tableName = $table->name;
        $this->businessName = $businessName;
        $this->cart = session()->get($this->cartKey(), []);
    }

    #[Computed]
    public function categories()
    {
        return Category::query()->whereHas('items', fn ($query) => $query->where('active', true))
            ->with(['items' => fn ($query) => $query->where('active', true)
                ->when($this->search, fn ($query) => $query->where(fn ($query) => $query
                    ->where('description', 'like', '%'.$this->search.'%')->orWhere('code', 'like', '%'.$this->search.'%')
                    ->orWhere('notes', 'like', '%'.$this->search.'%')))
                ->orderBy('code')->with(['photos', 'modifierGroups.options'])])
            ->orderBy('sort_order')->orderBy('id')->get();
    }

    #[Computed]
    public function selectedItem(): ?Item
    {
        return $this->selectedItemId ? Item::query()->where('active', true)
            ->with(['photos', 'modifierGroups.options'])->find($this->selectedItemId) : null;
    }

    #[Computed]
    public function pricing(): ?array
    {
        return $this->selectedItem ? app(PriceOrderItem::class)->calculate($this->selectedItem, $this->selections) : null;
    }

    public function openItem(int $itemId): void
    {
        $item = Item::query()->where('active', true)->with('modifierGroups')->findOrFail($itemId);
        $this->reset(['selections', 'quantity', 'notes']);
        foreach ($item->modifierGroups as $group) {
            $this->selections[$group->id] = $group->max_select === 1 ? null : [];
        }
        $this->resetValidation();
        $this->selectedItemId = $itemId;
        $this->showItem = true;
    }

    public function changeQuantity(int $change): void
    {
        $this->quantity = max(1, min(99, $this->quantity + $change));
    }

    public function addToCart(): void
    {
        $this->validate(['quantity' => 'required|integer|min:1|max:99', 'notes' => 'nullable|string|max:255']);
        $item = Item::query()->where('active', true)->with('modifierGroups.options')->find($this->selectedItemId);
        if (! $item) {
            $this->addError('item', 'This item is no longer available.');
            return;
        }
        $priced = app(PriceOrderItem::class)->calculate($item, $this->selections);
        foreach ($priced['errors'] as $id => $message) {
            $this->addError('selections.'.$id, $message);
        }
        if ($priced['errors'] !== []) {
            return;
        }
        $this->cart[] = [
            'key' => (string) Str::uuid(), 'item_id' => $item->id, 'name' => $item->description,
            'quantity' => $this->quantity, 'notes' => trim($this->notes),
            'unit_price' => $priced['unit_price'], 'modifiers' => $priced['modifiers'],
        ];
        $this->saveCart();
        $this->showItem = false;
    }

    public function changeCartQuantity(string $key, int $change): void
    {
        if (! in_array($change, [-1, 1], true)) {
            return;
        }
        foreach ($this->cart as &$line) {
            if ($line['key'] === $key) {
                $line['quantity'] = max(1, min(99, $line['quantity'] + $change));
                break;
            }
        }
        unset($line);
        $this->saveCart();
    }

    public function removeFromCart(string $key): void
    {
        $this->cart = array_values(array_filter($this->cart, fn ($line) => $line['key'] !== $key));
        $this->saveCart();
    }

    public function getCartCountProperty(): int
    {
        return array_sum(array_column($this->cart, 'quantity'));
    }

    public function getCartTotalProperty(): float
    {
        return round(array_sum(array_map(fn ($line) => $line['unit_price'] * $line['quantity'], $this->cart)), 2);
    }

    private function cartKey(): string
    {
        return 'customer_cart.'.$this->tableId;
    }

    private function saveCart(): void
    {
        session()->put($this->cartKey(), $this->cart);
    }
}; ?>

<div class="customer-menu" x-data="{ active: 'all', go(id) { this.active = id; document.getElementById(id === 'all' ? 'menu-start' : id)?.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth', block: 'start' }) }, update() { let current = 'all'; document.querySelectorAll('[data-menu-section]').forEach(section => { if (section.getBoundingClientRect().top <= 150) current = section.id }); this.active = current } }" @scroll.window.throttle.100ms="update()">
    <header class="menu-header">
        <div><p class="customer-eyebrow">Welcome to</p><h1>{{ $businessName }}</h1></div>
        <span class="table-badge"><span>You're seated at</span>Table {{ $tableName }}</span>
    </header>
    <main class="menu-main">
        <div class="menu-intro"><h2>What sounds good?</h2><p>Choose your favourites, made your way.</p></div>
        <div class="menu-search"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg><label class="sr-only" for="menu-search">Search the menu</label><input id="menu-search" type="search" wire:model.live.debounce.300ms="search" placeholder="Search dishes and drinks">@if ($search !== '')<button type="button" wire:click="$set('search', '')" aria-label="Clear search">×</button>@endif</div>
        <nav class="category-nav" aria-label="Menu categories">
            <button type="button" :class="{ 'active': active === 'all' }" :aria-pressed="active === 'all'" @click="go('all')">All dishes</button>
            @foreach ($this->categories as $category)
                <button type="button" wire:key="category-pill-{{ $category->id }}" :class="{ 'active': active === 'category-{{ $category->id }}' }" :aria-pressed="active === 'category-{{ $category->id }}'" @click="go('category-{{ $category->id }}')">{{ $category->description }}</button>
            @endforeach
        </nav>
        <div id="menu-start" class="menu-sections">
            @foreach ($this->categories as $category)
                @if ($category->items->isNotEmpty())
                    <section id="category-{{ $category->id }}" data-menu-section wire:key="category-{{ $category->id }}" class="menu-section">
                        <div class="section-heading"><h2>{{ $category->description }}</h2><span>{{ $category->items->count() }} choices</span></div>
                        <div class="item-list">
                            @foreach ($category->items as $item)
                                @php $photo = $item->photos->firstWhere('is_primary', true) ?? $item->photos->first(); @endphp
                                <article class="menu-item" wire:key="menu-item-{{ $item->id }}">
                                    <button type="button" class="item-photo" wire:click="openItem({{ $item->id }})" aria-label="View {{ $item->description }}">
                                        @if ($photo)<img src="{{ route('customer-order.photo', $photo) }}" loading="lazy" decoding="async" width="96" height="96" alt="">@else<svg aria-hidden="true" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 31h40c-1 13-9 20-20 20s-19-7-20-20Z"/><path d="M22 53h20M24 23c-6-6 6-8 0-14m10 14c-6-6 6-8 0-14m10 14c-6-6 6-8 0-14" stroke-linecap="round"/></svg>@endif
                                    </button>
                                    <button type="button" class="item-copy text-left" wire:click="openItem({{ $item->id }})"><h3>{{ $item->description }}</h3>@if ($item->notes)<p>{{ Str::limit($item->notes, 75) }}</p>@endif @if ($item->is_combo)<span class="combo-badge">Combo</span>@endif <strong>RM{{ number_format((float) $item->unit_price, 2) }}</strong></button>
                                    <button type="button" class="add-button" wire:click="openItem({{ $item->id }})" aria-label="Add {{ $item->description }}">+</button>
                                </article>
                            @endforeach
                        </div>
                    </section>
                @endif
            @endforeach
            @if ($this->categories->every(fn ($category) => $category->items->isEmpty()))<div class="menu-empty"><h2>{{ $search ? 'No dishes found' : 'The kitchen is getting ready' }}</h2><p>{{ $search ? 'Try another name, or browse the full menu.' : 'Please ask our team what is available today.' }}</p>@if ($search)<button type="button" wire:click="$set('search', '')">Show all dishes</button>@endif</div>@endif
        </div>
    </main>
    @if ($this->cartCount > 0)
        <div class="cart-bar"><button type="button" wire:click="$set('showCart', true)"><span class="cart-count">{{ $this->cartCount }}</span><span class="cart-bar-label">View your order<span>{{ $this->cartCount === 1 ? '1 item' : $this->cartCount.' items' }} added</span></span><strong>RM{{ number_format($this->cartTotal, 2) }} <span aria-hidden="true">→</span></strong></button></div>
    @endif
    @if ($showItem && $this->selectedItem)
        @php $item = $this->selectedItem; $pricing = $this->pricing; @endphp
        <div class="sheet-backdrop" wire:click.self="$set('showItem', false)" wire:keydown.escape="$set('showItem', false)">
            <section class="menu-sheet" role="dialog" aria-modal="true" aria-labelledby="item-sheet-title" x-trap.inert.noscroll="true">
                <div class="sheet-handle"></div><button type="button" class="sheet-close" wire:click="$set('showItem', false)" aria-label="Close item details">×</button>
                <div class="sheet-content">
                    @if ($item->photos->isNotEmpty())<div class="sheet-photos">@foreach ($item->photos as $photo)<img src="{{ route('customer-order.photo', $photo) }}" loading="lazy" decoding="async" alt="{{ $item->description }}">@endforeach</div>@endif
                    <p class="customer-eyebrow">Make it yours</p><h2 id="item-sheet-title">{{ $item->description }}</h2><p class="sheet-price">RM{{ number_format((float) $item->unit_price, 2) }}</p>
                    @if ($item->notes)<p>{{ $item->notes }}</p>@endif
                    @error('item')<p role="alert" class="menu-error">{{ $message }}</p>@enderror
                    @foreach ($item->modifierGroups as $group)
                        <fieldset class="modifier-group" wire:key="modifier-{{ $group->id }}"><legend>{{ $group->name }} <span class="requirement-badge">{{ $group->min_select ? 'Required' : 'Optional' }}</span></legend><p>{{ $group->min_select === $group->max_select ? 'Choose '.$group->max_select : ($group->min_select ? 'Choose '.$group->min_select.'–'.$group->max_select : 'Choose up to '.$group->max_select) }} · {{ count(app(PriceOrderItem::class)->selectedOptionIds($group, $selections)) }} selected</p>
                            <div class="modifier-options">@if ($group->max_select === 1 && $group->min_select === 0)<button type="button" class="modifier-clear" wire:click="$set('selections.{{ $group->id }}', null)">None</button>@endif @foreach ($group->options as $option)
                                <label><input type="{{ $group->max_select === 1 ? 'radio' : 'checkbox' }}" wire:model.live="selections.{{ $group->id }}" value="{{ $option->id }}" @if ($group->max_select === 1) name="group-{{ $group->id }}" @endif><span>{{ $option->name_en }} @if ($option->name_zh) / {{ $option->name_zh }} @endif @if ((float) $option->price_adjustment !== 0.0) {{ (float) $option->price_adjustment > 0 ? '+' : '-' }}RM{{ number_format(abs((float) $option->price_adjustment), 2) }} @endif</span></label>
                            @endforeach</div>@error('selections.'.$group->id)<p role="alert" class="menu-error">{{ $message }}</p>@enderror
                        </fieldset>
                    @endforeach
                    <label class="notes-label">Notes <span>(optional)</span><textarea wire:model="notes" maxlength="255" rows="2" placeholder="Anything we should know?"></textarea></label>
                    @error('notes')<p role="alert" class="menu-error">{{ $message }}</p>@enderror
                    <div class="quantity-row"><span>Quantity</span><div class="stepper"><button type="button" wire:click="changeQuantity(-1)" aria-label="Decrease quantity">−</button><span>{{ $quantity }}</span><button type="button" wire:click="changeQuantity(1)" aria-label="Increase quantity">+</button></div></div>
                </div>
                <div class="sheet-footer">@if ($pricing && $pricing['errors'] !== [])<p class="selection-hint" role="status">{{ reset($pricing['errors']) }}</p>@endif<button type="button" class="primary-action" wire:click="addToCart" wire:loading.attr="disabled" @disabled(! $pricing || $pricing['errors'] !== [])><span wire:loading.remove wire:target="addToCart">Add to order · RM{{ number_format(($pricing['unit_price'] ?? 0) * $quantity, 2) }}</span><span wire:loading wire:target="addToCart">Adding to your order…</span></button></div>
            </section>
        </div>
    @endif
    @if ($showCart)
        <div class="sheet-backdrop" wire:click.self="$set('showCart', false)" wire:keydown.escape="$set('showCart', false)">
            <section class="menu-sheet" role="dialog" aria-modal="true" aria-labelledby="cart-sheet-title" x-trap.inert.noscroll="true"><div class="sheet-handle"></div><button type="button" class="sheet-close" wire:click="$set('showCart', false)" aria-label="Close order">×</button>
                <div class="sheet-content"><p class="customer-eyebrow">Table {{ $tableName }}</p><h2 id="cart-sheet-title">Your order</h2>
                    @forelse ($cart as $line)
                        <div class="cart-line" wire:key="cart-{{ $line['key'] }}"><div><strong>{{ $line['name'] }}</strong><p>{{ collect($line['modifiers'])->pluck('name_en')->join(', ') }}</p>@if ($line['notes'])<p>{{ $line['notes'] }}</p>@endif<strong>RM{{ number_format($line['unit_price'] * $line['quantity'], 2) }}</strong></div><div class="cart-actions"><div class="stepper"><button type="button" wire:click="changeCartQuantity('{{ $line['key'] }}', -1)" aria-label="Decrease {{ $line['name'] }} quantity">−</button><span>{{ $line['quantity'] }}</span><button type="button" wire:click="changeCartQuantity('{{ $line['key'] }}', 1)" aria-label="Increase {{ $line['name'] }} quantity">+</button></div><button type="button" class="remove-line" wire:click="removeFromCart('{{ $line['key'] }}')">Remove</button></div></div>
                    @empty<p>Your order is empty. Add something delicious from the menu.</p>@endforelse
                </div><div class="sheet-footer"><div class="cart-total"><strong>Total</strong><strong>RM{{ number_format($this->cartTotal, 2) }}</strong></div><button type="button" class="secondary-action" wire:click="$set('showCart', false)">Keep browsing</button><p class="selection-hint">Ordering from this menu will be available soon.</p><button type="button" class="primary-action" disabled>Place order · Coming soon</button></div>
            </section>
        </div>
    @endif
</div>
