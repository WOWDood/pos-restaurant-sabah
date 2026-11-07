<?php

use App\Actions\StoreShopLogo;
use App\Models\BusinessSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

new #[Title('Shop Settings')] class extends Component {
    use WithFileUploads;

    public string $businessName = '';
    public string $address = '';
    public string $phone = '';
    public string $email = '';
    public string $menuMessage = '';
    public string $receiptFooter = '';
    public bool $taxEnabled = false;
    public string $taxName = 'Tax';
    public string $taxRate = '0.00';
    public $logo;
    public bool $removeLogo = false;

    public function boot(): void
    {
        Gate::authorize('manage-shop');
    }

    public function mount(): void
    {
        $this->loadSettings();
    }

    public function loadSettings(): void
    {
        $settings = BusinessSetting::settings();
        $this->businessName = $settings['business_name'];
        $this->address = $settings['location'];
        $this->phone = $settings['phone'];
        $this->email = $settings['email'];
        $this->menuMessage = $settings['menu_message'];
        $this->receiptFooter = $settings['receipt_footer'];
        $this->taxEnabled = $settings['tax_enabled'] === '1';
        $this->taxName = $settings['tax_name'];
        $this->taxRate = $settings['tax_rate'];
        $this->reset('logo', 'removeLogo');
        $this->resetValidation();
    }

    public function getLogoUrlProperty(): ?string
    {
        return BusinessSetting::logoUrl(BusinessSetting::settings());
    }

    public function save(): void
    {
        $this->businessName = trim($this->businessName);
        $this->taxName = trim($this->taxName);
        $data = $this->validate([
            'businessName' => ['required', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:1000'],
            'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:255'],
            'menuMessage' => ['nullable', 'string', 'max:255'],
            'receiptFooter' => ['nullable', 'string', 'max:255'],
            'taxEnabled' => ['boolean'],
            'taxName' => ['required', 'string', 'max:40'],
            'taxRate' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=4096,max_height=4096'],
            'removeLogo' => ['boolean'],
        ]);

        $newPath = $this->logo ? app(StoreShopLogo::class)->handle($this->logo) : null;
        $oldPath = null;

        try {
            DB::transaction(function () use ($data, $newPath, &$oldPath): void {
                $oldPath = BusinessSetting::query()->where('key', 'logo_path')->lockForUpdate()->value('value');
                $settings = [
                    'business_name' => $data['businessName'], 'location' => trim($data['address']),
                    'phone' => trim($data['phone']), 'email' => trim($data['email']),
                    'menu_message' => trim($data['menuMessage']), 'receipt_footer' => trim($data['receiptFooter']),
                    'tax_enabled' => $data['taxEnabled'] ? '1' : '0', 'tax_name' => $data['taxName'],
                    'tax_rate' => number_format((float) $data['taxRate'], 2, '.', ''),
                ];
                if ($newPath !== null || $data['removeLogo']) {
                    $settings['logo_path'] = $newPath ?? '';
                }
                foreach ($settings as $key => $value) {
                    BusinessSetting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
                }
            });
        } catch (\Throwable $exception) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $exception;
        }

        if (($newPath !== null || $this->removeLogo) && is_string($oldPath) && str_starts_with($oldPath, 'shop-logos/')) {
            Storage::disk('local')->delete($oldPath);
        }

        session()->flash('action_success', 'Shop settings saved.');
        $this->redirect(route('admin.shop-settings'), navigate: true);
    }
}; ?>

<section class="shop-settings-page pos-light-surface space-y-6">
    <x-page-heading title="Shop Settings" description="Configure restaurant information, branding, tax settings, and customer messages." />
    <x-shop-settings-nav />
    <x-action-feedback />
    <form wire:submit="save" class="space-y-6">
        <div class="grid gap-6 xl:grid-cols-2">
            <x-card class="min-w-0 space-y-5">
                <div><h2 class="text-lg font-semibold">Restaurant Details</h2><p class="mt-1 text-gray-600">Shown on your customer menu, sidebar, and receipts.</p></div>
                <flux:input wire:model="businessName" label="Restaurant Name" maxlength="120" required />
                <flux:textarea wire:model="address" label="Shop Address" rows="3" maxlength="1000" />
                <div class="grid items-start gap-5 sm:grid-cols-2">
                    <flux:input wire:model="phone" label="Phone Number" type="tel" autocomplete="tel" maxlength="40" />
                    <flux:input wire:model="email" label="Contact Email" type="email" autocomplete="email" maxlength="255" />
                </div>
            </x-card>
            <x-card class="min-w-0 space-y-5">
                <div><h2 class="text-lg font-semibold">Restaurant Logo</h2><p class="mt-1 text-gray-600">Appears beside your restaurant name in the sidebar and on the QR menu.</p></div>
                <div class="flex flex-wrap items-center gap-4">
                    <div class="grid size-24 shrink-0 place-items-center overflow-hidden rounded-2xl border border-gray-300 bg-gray-50 p-2">
                        @if ($logo && in_array($logo->getMimeType(), ['image/png', 'image/jpeg', 'image/webp'], true))
                            <img src="{{ $logo->temporaryUrl() }}" alt="New restaurant logo preview" class="size-full object-contain" />
                        @elseif ($this->logoUrl && ! $removeLogo)
                            <img src="{{ $this->logoUrl }}" alt="Current restaurant logo" class="size-full object-contain" />
                        @else
                            <span class="text-center text-sm text-gray-500">No logo uploaded</span>
                        @endif
                    </div>
                    <div class="min-w-0 flex-1 space-y-2">
                        <label for="shop-logo" class="block font-medium text-gray-700">Upload Logo</label>
                        <input id="shop-logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="block w-full min-w-0 text-sm file:mr-3 file:rounded-lg file:border file:border-indigo-200 file:bg-indigo-50 file:px-3 file:py-2 file:text-indigo-800" />
                        <p class="text-sm text-gray-600">PNG, JPEG, or WebP. Up to 2 MB, 32–4096 pixels per side. Transparent PNG works well.</p>
                        <p wire:loading wire:target="logo" role="status" class="text-indigo-800">Uploading logo…</p>
                        @error('logo') <p role="alert" class="text-sm text-red-700">{{ $message }}</p> @enderror
                    </div>
                </div>
                @if ($this->logoUrl)
                    <label class="flex min-h-11 items-center gap-3"><input type="checkbox" wire:model.live="removeLogo" class="size-4 accent-indigo-700" />Remove the current logo when saving</label>
                @endif
            </x-card>
        </div>
        <div class="grid gap-6 xl:grid-cols-2">
            <x-card class="min-w-0 space-y-5">
                <div><h2 class="text-lg font-semibold">Tax Settings</h2><p class="mt-1 text-gray-600">Tax is added to the item subtotal. Existing bills keep the tax settings they were opened with.</p></div>
                <label class="flex min-h-11 items-center gap-3 font-medium"><input type="checkbox" wire:model.live="taxEnabled" class="size-5 accent-indigo-700" />Enable Tax</label>
                <div class="grid items-start gap-5 sm:grid-cols-2">
                    <flux:input wire:model="taxName" label="Tax Label" description:trailing="For example: SST or Service Tax" maxlength="40" required />
                    <flux:input wire:model="taxRate" label="Tax Rate (%)" type="number" min="0" max="100" step="0.01" required />
                </div>
                <p class="rounded-lg bg-indigo-50 px-4 py-3 text-sm text-indigo-900">All prices and receipts use Malaysian Ringgit (RM). Tax is rounded to the nearest sen per bill.</p>
            </x-card>
            <x-card class="min-w-0 space-y-5">
                <div><h2 class="text-lg font-semibold">Customer Messages</h2><p class="mt-1 text-gray-600">Configure the welcome message on the customer menu and the closing message on receipts.</p></div>
                <flux:textarea wire:model="menuMessage" label="Menu Welcome Message" rows="2" maxlength="255" />
                <flux:textarea wire:model="receiptFooter" label="Receipt Thank-You Message" rows="2" maxlength="255" />
            </x-card>
        </div>
        <div class="row-actions">
            <button type="submit" class="row-action row-action-primary pending-action" wire:loading.attr="disabled" wire:target="save,logo"><span wire:loading.remove wire:target="save">Save Shop Settings</span><span wire:loading wire:target="save" role="status">Saving…</span></button>
            <button type="button" wire:click="loadSettings" class="row-action row-action-neutral" wire:loading.attr="disabled" wire:target="save,logo">Discard Changes</button>
        </div>
    </form>
</section>
