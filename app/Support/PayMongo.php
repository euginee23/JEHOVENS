<?php

namespace App\Support;

use App\Exceptions\PayMongoException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * The resort's window onto PayMongo.
 *
 * Everything that talks to the gateway goes through here, so the JSON shapes, the auth
 * scheme and the peso/centavo conversion live in one file rather than being rediscovered
 * at each call site.
 */
class PayMongo
{
    /**
     * PayMongo counts in centavos; every money column in this application is whole pesos.
     */
    public const CENTAVOS_PER_PESO = 100;

    /**
     * The smallest payment PayMongo will take, in pesos.
     */
    public const MINIMUM_PESOS = 20;

    /**
     * A client built from the configured credentials.
     */
    public static function make(): self
    {
        return new self;
    }

    /**
     * Whether the resort is set up to take payments at all.
     *
     * Checked before a booking page offers to take one: without a key the guest would be
     * sent nowhere, and a reservation nobody paid for would be left holding the dates.
     */
    public static function isConfigured(): bool
    {
        return filled(config('services.paymongo.secret_key'));
    }

    /**
     * Whole pesos as the centavos PayMongo expects.
     */
    public static function centavos(int $pesos): int
    {
        return $pesos * self::CENTAVOS_PER_PESO;
    }

    /**
     * Centavos back to the whole pesos this application stores.
     *
     * Rounded rather than truncated: the resort only ever sends whole-peso amounts, so a
     * centavo adrift is a rounding artefact and not a real part-payment.
     */
    public static function pesos(int $centavos): int
    {
        return (int) round($centavos / self::CENTAVOS_PER_PESO);
    }

    /**
     * Open a checkout session and get back the URL to send the guest to.
     *
     * @throws PayMongoException
     */
    public function createCheckoutSession(CheckoutRequest $request): CheckoutSession
    {
        if ($request->amount < self::MINIMUM_PESOS) {
            throw new PayMongoException(
                'PayMongo will not take a payment under ₱'.self::MINIMUM_PESOS.", and this one is ₱{$request->amount}."
            );
        }

        $response = $this->client()->post('/checkout_sessions', $request->toPayload());

        if ($response->failed()) {
            throw PayMongoException::fromResponse($response->status(), $response->json() ?? []);
        }

        return CheckoutSession::fromResponse($response->json() ?? []);
    }

    /**
     * Ask PayMongo what actually became of a checkout session.
     *
     * Used on the guest's return from checkout, because a redirect only proves they got
     * back to us — not that any money moved.
     *
     * @throws PayMongoException
     */
    public function retrieveCheckoutSession(string $sessionId): CheckoutSession
    {
        $response = $this->client()->get("/checkout_sessions/{$sessionId}");

        if ($response->failed()) {
            throw PayMongoException::fromResponse($response->status(), $response->json() ?? []);
        }

        return CheckoutSession::fromResponse($response->json() ?? []);
    }

    /**
     * Close a checkout session the guest is never coming back to.
     *
     * Best effort: the dates are released either way, and a session PayMongo has already
     * expired on its own refuses this.
     */
    public function expireCheckoutSession(string $sessionId): void
    {
        $this->client()->post("/checkout_sessions/{$sessionId}/expire");
    }

    /**
     * An HTTP client pointed at PayMongo, authenticated as this resort.
     *
     * The secret key is the basic-auth *username* with no password, which is how PayMongo
     * authenticates server-to-server calls.
     */
    protected function client(): PendingRequest
    {
        return Http::baseUrl((string) config('services.paymongo.base_url'))
            ->withBasicAuth((string) config('services.paymongo.secret_key'), '')
            ->acceptJson()
            ->asJson()
            ->timeout(15)
            // Retried on a dropped connection, not on a rejection: a 4xx means the
            // request was wrong and sending it again would only be wrong twice.
            ->retry(2, 250, throw: false);
    }
}
