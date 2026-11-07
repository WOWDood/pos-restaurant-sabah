<?php

use App\Models\BusinessSetting;
use App\Models\RestaurantTable;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

it('allows only administrators to open and update shop settings', function () {
    $this->get(route('admin.shop-settings'))->assertRedirect(route('login'));
    $staff = User::factory()->create(['user_type' => User::TYPE_STAFF]);
    $this->actingAs($staff)->get(route('admin.shop-settings'))->assertForbidden();
    Livewire::test('pages::shop-settings')->assertForbidden();
});

it('saves shop details and shows them in the sidebar and customer menu', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    $table = RestaurantTable::factory()->create();
    Livewire::actingAs($admin)->test('pages::shop-settings')
        ->set('businessName', 'XE Kitchen')->set('address', "42 Jalan Sabah\nKota Kinabalu")
        ->set('phone', '088-123456')->set('email', 'hello@example.com')
        ->set('menuMessage', 'Welcome, choose your favourites.')
        ->set('receiptFooter', 'See you again!')->set('taxEnabled', true)
        ->set('taxName', 'Service tax')->set('taxRate', '6')
        ->call('save')->assertHasNoErrors()->assertRedirect(route('admin.shop-settings'));

    expect(BusinessSetting::settings())->toMatchArray([
        'business_name' => 'XE Kitchen', 'location' => "42 Jalan Sabah\nKota Kinabalu",
        'phone' => '088-123456', 'email' => 'hello@example.com', 'tax_enabled' => '1',
        'tax_rate' => '6.00', 'receipt_footer' => 'See you again!',
    ]);
    $this->get(route('admin.shop-settings'))->assertOk()->assertSee('XE Kitchen')->assertSee('Staff Roles');
    $this->get(route('customer-order.create', $table))->assertOk()
        ->assertSee('XE Kitchen')->assertSee('42 Jalan Sabah')
        ->assertSee('088-123456')->assertSee('Welcome, choose your favourites.')
        ->assertSee('Service tax')->assertSee('6.00%');
});

it('rejects invalid settings without saving', function (string $field, mixed $value) {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    Livewire::actingAs($admin)->test('pages::shop-settings')
        ->set($field, $value)->call('save')->assertHasErrors($field);
    expect(BusinessSetting::count())->toBe(0);
})->with([
    'blank name' => ['businessName', '   '],
    'long name' => ['businessName', str_repeat('a', 121)],
    'invalid email' => ['email', 'not-an-email'],
    'negative tax' => ['taxRate', '-1'],
    'tax over 100' => ['taxRate', '101'],
    'tax with too many decimal places' => ['taxRate', '6.555'],
    'empty tax label' => ['taxName', ''],
    'long address' => ['address', str_repeat('a', 1001)],
]);

it('stores a small safe logo and replaces or removes it only after saving', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    Livewire::actingAs($admin)->test('pages::shop-settings')
        ->set('logo', UploadedFile::fake()->image('brand.png', 800, 400))
        ->call('save')->assertHasNoErrors();
    $path = BusinessSetting::settings()['logo_path'];
    Storage::disk('local')->assertExists($path);
    $response = $this->get(route('shop.logo'))->assertOk()->assertHeader('Content-Type', 'image/png');
    $dimensions = getimagesizefromstring($response->getContent());
    expect($dimensions[0])->toBe(320)->and($dimensions[1])->toBe(160);
    $this->get(route('profile'))->assertOk()->assertSee('shop/logo', false);

    $component = Livewire::test('pages::shop-settings')->set('removeLogo', true);
    Storage::disk('local')->assertExists($path);
    $component->call('loadSettings')->assertSet('removeLogo', false);
    $component->set('logo', UploadedFile::fake()->image('replacement.jpg', 100, 100))
        ->call('save')->assertHasNoErrors();
    Storage::disk('local')->assertMissing($path);
    $replacement = BusinessSetting::settings()['logo_path'];
    expect($replacement)->not->toBe($path);

    Livewire::test('pages::shop-settings')->set('removeLogo', true)->call('save')->assertHasNoErrors();
    Storage::disk('local')->assertMissing($replacement);
    $this->get(route('shop.logo'))->assertNotFound();
});

it('rejects unsafe logo files', function () {
    Storage::fake('local');
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    Livewire::actingAs($admin)->test('pages::shop-settings')
        ->set('logo', UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml'))
        ->call('save')->assertHasErrors('logo');
    expect(Storage::disk('local')->allFiles('shop-logos'))->toBeEmpty();
});

it('rejects oversized and oversized-dimension logos', function (int $width, int $size) {
    Storage::fake('local');
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    Livewire::actingAs($admin)->test('pages::shop-settings')
        ->set('logo', UploadedFile::fake()->image('brand.png', $width, 100)->size($size))
        ->call('save')->assertHasErrors('logo');
    expect(Storage::disk('local')->allFiles('shop-logos'))->toBeEmpty();
})->with(['large file' => [100, 2049], 'large dimensions' => [4097, 10]]);

it('does not serve private files outside the logo directory', function () {
    Storage::fake('local');
    Storage::disk('local')->put('private.txt', 'private');
    BusinessSetting::create(['key' => 'logo_path', 'value' => 'private.txt']);
    $this->get(route('shop.logo'))->assertNotFound();
});

it('rejects updates if the administrator has lost access', function () {
    $admin = User::factory()->create(['user_type' => User::TYPE_ADMIN]);
    $component = Livewire::actingAs($admin)->test('pages::shop-settings');
    $admin->update(['user_type' => User::TYPE_STAFF]);
    $component->set('businessName', 'Forbidden change')->assertForbidden();
    expect(BusinessSetting::count())->toBe(0);
});

it('escapes shop text in the public menu', function () {
    $table = RestaurantTable::factory()->create();
    BusinessSetting::create(['key' => 'business_name', 'value' => '<script>alert(1)</script>']);
    $this->get(route('customer-order.create', $table))->assertOk()
        ->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
});
