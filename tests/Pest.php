<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * The URL a faked checkout session sends guests to.
 */
const FAKE_CHECKOUT_URL = 'https://checkout.paymongo.com/cs_test_fake';

/**
 * Stand in for PayMongo, so no test ever reaches the real gateway.
 *
 * `preventStrayRequests()` is the important half: a call this forgets to fake fails the
 * test loudly rather than quietly trying to reach the internet from CI.
 */
function fakePayMongo(bool $paid = false, int $paidAmount = 0): void
{
    Http::preventStrayRequests();

    Http::fake([
        '*/checkout_sessions*' => Http::response(
            ['data' => fakeCheckoutSession($paid, $paidAmount)],
        ),
    ]);
}

/**
 * One checkout session as PayMongo describes it, paid or not.
 *
 * @return array<string, mixed>
 */
function fakeCheckoutSession(bool $paid = false, int $paidAmount = 0): array
{
    return [
        'id' => 'cs_test_fake',
        'attributes' => [
            'checkout_url' => FAKE_CHECKOUT_URL,
            'reference_number' => 'JGR-TEST',
            'payment_intent' => ['id' => 'pi_test_fake'],
            'payments' => $paid ? [[
                'id' => 'pay_test_fake',
                'attributes' => [
                    'status' => 'paid',
                    // PayMongo counts in centavos.
                    'amount' => $paidAmount * 100,
                    'source' => ['type' => 'gcash'],
                ],
            ]] : [],
        ],
    ];
}
