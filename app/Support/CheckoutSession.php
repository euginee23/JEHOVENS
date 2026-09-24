<?php

namespace App\Support;

/**
 * A PayMongo checkout session, flattened to the parts this application uses.
 *
 * Only ever built by {@see PayMongo}, so the gateway's JSON shape stays in one place and
 * everything else reads plain properties.
 */
readonly class CheckoutSession
{
    /**
     * @param  array<string, mixed>  $raw  the body as PayMongo sent it
     */
    public function __construct(
        public string $id,
        public string $checkoutUrl,
        public ?string $paymentIntentId,
        public ?string $paymentId,
        public ?string $paymentMethod,
        public ?int $paidAmount,
        public bool $isPaid,
        public array $raw = [],
    ) {}

    /**
     * Read a session out of a PayMongo response body.
     *
     * A session is only treated as paid once a payment on it says so: the guest arriving
     * back on the success URL proves they reached the page, not that they paid.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromResponse(array $body): self
    {
        $attributes = data_get($body, 'data.attributes', []);
        $payment = null;

        foreach ((array) data_get($attributes, 'payments', []) as $candidate) {
            if (data_get($candidate, 'attributes.status') === 'paid') {
                $payment = $candidate;

                break;
            }
        }

        return new self(
            id: (string) data_get($body, 'data.id'),
            checkoutUrl: (string) data_get($attributes, 'checkout_url'),
            paymentIntentId: data_get($attributes, 'payment_intent.id'),
            paymentId: $payment ? (string) data_get($payment, 'id') : null,
            paymentMethod: $payment ? data_get($payment, 'attributes.source.type') : null,
            paidAmount: $payment ? PayMongo::pesos((int) data_get($payment, 'attributes.amount', 0)) : null,
            isPaid: $payment !== null,
            raw: $body,
        );
    }
}
