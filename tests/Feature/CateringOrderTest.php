<?php

use App\Enums\BookingStatus;
use App\Models\CateringOrder;
use App\Models\CateringPackage;
use App\Models\CateringPackagePhoto;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

/**
 * A Livewire test instance with the catering order form filled in.
 */
function fillCateringOrder(CateringPackage $package, array $overrides = []): Testable
{
    $component = Livewire::test('pages::booking.catering');

    $input = array_merge([
        'package_id' => $package->id,
        'event_date' => now()->addMonth()->toDateString(),
        'guests' => 80,
        'include_skirting' => true,
        'guest_name' => 'Juan dela Cruz',
        'guest_phone' => '09171234567',
        'guest_email' => 'juan@example.com',
    ], $overrides);

    foreach ($input as $field => $value) {
        $component->set($field, $value);
    }

    return $component;
}

beforeEach(function () {
    $this->package = CateringPackage::factory()->create([
        'name' => 'Mediterranean Mezze',
        'price_per_head' => 450,
        'skirting_price' => 5000,
        'minimum_guests' => 20,
    ]);
});

test('the ordering page renders with the available packages', function () {
    CateringPackage::factory()->inactive()->create(['name' => 'Off The Menu']);

    $this->get(route('booking.catering'))
        ->assertOk()
        ->assertSee('Order catering services')
        ->assertSee('Mediterranean Mezze')
        ->assertSee('₱450 per head')
        ->assertSee('Min. 20 guests')
        ->assertDontSee('Off The Menu');
});

test('the page says so when nothing is on the menu', function () {
    CateringPackage::query()->delete();

    $this->get(route('booking.catering'))
        ->assertOk()
        ->assertSee('No catering packages are available right now.');
});

test('catering is priced per head plus a one-time skirting fee', function () {
    expect($this->package->quote(guests: 80, includeSkirting: true))
        ->toMatchArray([
            'catering_total' => 36_000,
            'skirting_total' => 5_000,
            'total' => 41_000,
            'downpayment' => 20_500,
            'balance' => 20_500,
        ]);
});

test('skirting is left out of the quote when it is not wanted', function () {
    expect($this->package->quote(guests: 80, includeSkirting: false))
        ->toMatchArray(['total' => 36_000, 'downpayment' => 18_000, 'balance' => 18_000]);
});

test('an odd total rounds the downpayment up', function () {
    $package = CateringPackage::factory()->create(['price_per_head' => 475, 'skirting_price' => 0, 'minimum_guests' => 1]);

    expect($package->quote(guests: 3, includeSkirting: false))
        ->toMatchArray(['total' => 1_425, 'downpayment' => 713, 'balance' => 712]);
});

test('the live price summary follows the head count', function () {
    fillCateringOrder($this->package)
        ->assertSee('₱36,000')
        ->assertSee('₱41,000')
        ->assertSee('₱20,500')
        ->set('guests', 100)
        ->assertSee('₱45,000')
        ->assertSee('₱50,000')
        ->assertSee('₱25,000');
});

test('a guest can order catering without an account', function () {
    fillCateringOrder($this->package)
        ->call('proceedToPayment')
        ->assertHasNoErrors()
        ->assertSet('showPayment', true)
        ->call('confirmPayment')
        ->assertHasNoErrors()
        ->assertSet('showPayment', false)
        ->assertSee('Order received');

    $order = CateringOrder::sole();

    expect($order)
        ->catering_package_id->toBe($this->package->id)
        ->user_id->toBeNull()
        ->guests->toBe(80)
        ->price_per_head->toBe(450)
        ->catering_total->toBe(36_000)
        ->skirting_total->toBe(5_000)
        ->total->toBe(41_000)
        ->downpayment->toBe(20_500)
        ->balance->toBe(20_500)
        ->status->toBe(BookingStatus::Pending)
        ->and($order->reference)->toStartWith('JGR-C');
});

test('the price per head is captured so later price changes do not rewrite the order', function () {
    fillCateringOrder($this->package)->call('confirmPayment')->assertHasNoErrors();

    $this->package->update(['price_per_head' => 900]);

    expect(CateringOrder::sole())
        ->price_per_head->toBe(450)
        ->total->toBe(41_000);
});

test('a signed-in guest has their order linked to their account', function () {
    $user = User::factory()->create(['name' => 'Maria Santos', 'email' => 'maria@example.com']);

    $this->actingAs($user);

    Livewire::test('pages::booking.catering')
        ->assertSet('guest_name', 'Maria Santos')
        ->assertSet('guest_email', 'maria@example.com');

    fillCateringOrder($this->package)->call('confirmPayment')->assertHasNoErrors();

    expect(CateringOrder::sole()->user_id)->toBe($user->id);
});

test('choosing a package defaults the head count to its minimum', function () {
    Livewire::test('pages::booking.catering')
        ->assertSet('guests', null)
        ->call('selectPackage', $this->package->id)
        ->assertSet('guests', 20);
});

test('choosing a package leaves a head count the guest already typed', function () {
    Livewire::test('pages::booking.catering')
        ->set('guests', 150)
        ->call('selectPackage', $this->package->id)
        ->assertSet('guests', 150);
});

test('a package must be chosen', function () {
    fillCateringOrder($this->package, ['package_id' => null])
        ->call('proceedToPayment')
        ->assertHasErrors(['package_id' => 'required']);
});

test('the event date cannot be in the past', function () {
    fillCateringOrder($this->package, ['event_date' => now()->subDay()->toDateString()])
        ->call('proceedToPayment')
        ->assertHasErrors(['event_date' => 'after_or_equal']);
});

test('a head count is required', function () {
    fillCateringOrder($this->package, ['guests' => null])
        ->call('proceedToPayment')
        ->assertHasErrors(['guests' => 'required']);
});

test('an order below the package minimum is rejected', function () {
    fillCateringOrder($this->package, ['guests' => 19])
        ->call('proceedToPayment')
        ->assertHasErrors('guests');

    expect(CateringOrder::count())->toBe(0);
});

test('an order at exactly the package minimum is accepted', function () {
    fillCateringOrder($this->package, ['guests' => 20])
        ->call('confirmPayment')
        ->assertHasNoErrors();

    expect(CateringOrder::sole()->guests)->toBe(20);
});

test('an implausibly large head count is rejected', function () {
    fillCateringOrder($this->package, ['guests' => CateringPackage::MAX_GUESTS + 1])
        ->call('proceedToPayment')
        ->assertHasErrors(['guests' => 'max']);
});

test('the phone number must be an 11-digit mobile number', function () {
    fillCateringOrder($this->package, ['guest_phone' => '12345'])
        ->call('proceedToPayment')
        ->assertHasErrors(['guest_phone' => 'regex']);
});

test('two orders can share the same event date', function () {
    $date = now()->addMonth()->toDateString();

    CateringOrder::factory()->for($this->package, 'package')->create(['event_date' => $date]);

    fillCateringOrder($this->package, ['event_date' => $date])
        ->call('confirmPayment')
        ->assertHasNoErrors();

    expect(CateringOrder::count())->toBe(2);
});

test('a package with photos shows them on the ordering page without dots', function () {
    $package = CateringPackage::factory()->create(['name' => 'Photographed Package']);

    CateringPackagePhoto::factory()->count(2)->for($package)->sequence(
        ['path' => 'catering/one.jpg'],
        ['path' => 'catering/two.jpg'],
    )->create();

    $html = $this->get(route('booking.catering'))->assertOk()->getContent();

    expect($html)->toContain('catering/one.jpg')
        ->and($html)->toContain('catering/two.jpg');

    $card = str($html)->after('wire:key="package-'.$package->id.'"')->before('</button>')->toString();

    expect($card)->toContain('x-data')
        ->and($card)->not->toContain('Show photo')
        ->and($card)->not->toContain('<button');
});

test('a package without photos still renders its card', function () {
    CateringPackage::factory()->create(['name' => 'Plain Package']);

    $this->get(route('booking.catering'))->assertOk()->assertSee('Plain Package');
});

test('the catering details panel is pinned', function () {
    $html = $this->get(route('booking.catering'))->getContent();
    $panel = str($html)->afterLast('bg-white p-6 shadow-sm shadow-brand-950/5 ring-1 ring-sand-200')->toString();

    expect($panel)->toContain('lg:sticky')
        ->and($panel)->toContain('lg:overflow-y-auto')
        ->and($html)->toContain('lg:items-start');
});

test('the catering panel prompts, then names the selection and its figures', function () {
    Livewire::test('pages::booking.catering')
        ->assertSee('Pick a package from the list to get started')
        ->call('selectPackage', $this->package->id)
        ->assertSee('Selected')
        ->assertSee('Mediterranean Mezze')
        ->assertSee('₱450 per head')
        ->assertSee('Skirting ₱5,000')
        ->assertSee('min. 20 guests')
        ->assertDontSee('Pick a package from the list');
});
