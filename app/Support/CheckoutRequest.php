<?php

namespace App\Support;

/**
 * What the resort wants a PayMongo checkout session to be.
 *
 * A readonly value object in the same spirit as {@see ReservationSummary}: the booking
 * pages describe the payment they want in the resort's own terms, and {@see PayMongo}
 * is the only thing that knows how PayMongo wants it spelled.
 */
readonly class CheckoutRequest
{
    /**
     * @param  int  $amount  in whole pesos — converted to centavos on the way out
     * @param  array<int, string>  $methods  what the guest may pay with
     * @param  array<string, string>  $metadata  echoed back on the webhook
     */
    public function __construct(
        public string $reference,
        public string $description,
        public string $lineItemName,
        public int $amount,
        public string $successUrl,
        public string $cancelUrl,
        public array $methods,
        public array $metadata = [],
        public ?string $guestName = null,
        public ?string $guestEmail = null,
        public ?string $guestPhone = null,
    ) {}

    /**
     * This request in the shape PayMongo's Checkout Sessions endpoint expects.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        $billing = array_filter([
            'name' => $this->guestName,
            'email' => $this->guestEmail,
            'phone' => $this->guestPhone,
        ]);

        return [
            'data' => [
                'attributes' => array_filter([
                    'billing' => $billing === [] ? null : $billing,
                    'line_items' => [[
                        'currency' => 'PHP',
                        'amount' => PayMongo::centavos($this->amount),
                        'name' => $this->lineItemName,
                        'quantity' => 1,
                    ]],
                    'payment_method_types' => array_values($this->methods),
                    'description' => $this->description,

                    // Shown on the guest's PayMongo receipt and handed back on the
                    // webhook, which is how a payment is matched to a booking.
                    'reference_number' => $this->reference,
                    'metadata' => $this->metadata,
                    'success_url' => $this->successUrl,
                    'cancel_url' => $this->cancelUrl,
                    'send_email_receipt' => true,
                    'show_description' => true,
                    'show_line_items' => true,
                ], fn (mixed $value) => $value !== null),
            ],
        ];
    }
}
