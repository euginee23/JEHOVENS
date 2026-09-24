<?php

namespace App\Http\Controllers;

use App\Support\CheckoutSession;
use App\Support\PayMongoPayments;
use App\Support\PayMongoSignature;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Where PayMongo tells the resort a booking has been paid for.
 *
 * This is a public URL, so nothing is believed until the signature checks out: without
 * that, anyone who guessed the address could confirm bookings for free.
 */
class PayMongoWebhookController extends Controller
{
    /**
     * Take one webhook delivery.
     */
    public function __invoke(Request $request): Response
    {
        // The raw body, not the parsed array: the signature covers the exact bytes
        // PayMongo sent, and re-encoding the JSON would change them.
        $payload = $request->getContent();

        if (! PayMongoSignature::verify(
            $request->header('Paymongo-Signature'),
            $payload,
            config('services.paymongo.webhook_secret'),
        )) {
            Log::warning('Rejected a PayMongo webhook with a bad signature.', ['ip' => $request->ip()]);

            return response()->noContent(400);
        }

        $body = $request->json()->all();
        $eventId = (string) data_get($body, 'data.id');
        $eventType = (string) data_get($body, 'data.attributes.type');

        if ($eventId === '') {
            return response()->noContent();
        }

        // PayMongo retries until it gets a 2xx and may deliver the same event twice.
        // Winning this insert is what earns the right to act on it; losing it means
        // another request already has, so this one quietly agrees.
        $isFirstDelivery = DB::table('payment_webhook_events')->insertOrIgnore([
            'provider' => 'paymongo',
            'event_id' => $eventId,
            'event_type' => $eventType,
            'payload' => $payload,
            'created_at' => now(),
            'updated_at' => now(),
        ]) > 0;

        if (! $isFirstDelivery) {
            return response()->noContent();
        }

        $this->handle($eventType, data_get($body, 'data.attributes.data', []));

        DB::table('payment_webhook_events')->where('event_id', $eventId)->update(['processed_at' => now()]);

        return response()->noContent();
    }

    /**
     * Act on one event, if it is one the resort cares about.
     *
     * Anything else is answered 200 and dropped, so PayMongo stops retrying events this
     * application was never going to do anything with.
     *
     * @param  array<string, mixed>  $resource  the checkout session the event is about
     */
    protected function handle(string $eventType, array $resource): void
    {
        $metadata = data_get($resource, 'attributes.metadata', []);
        $type = (string) data_get($metadata, 'type');
        $reference = (string) data_get($metadata, 'reference')
            ?: (string) data_get($resource, 'attributes.reference_number');

        if ($type === '' || $reference === '' || ! PayMongoPayments::isKnownType($type)) {
            Log::warning('A PayMongo webhook arrived without a booking to attach it to.', [
                'event' => $eventType,
                'metadata' => $metadata,
            ]);

            return;
        }

        match ($eventType) {
            'checkout_session.payment.paid' => PayMongoPayments::markPaid(
                $type,
                $reference,
                CheckoutSession::fromResponse(['data' => $resource]),
            ),
            'payment.failed' => PayMongoPayments::markFailed($type, $reference),
            default => null,
        };
    }
}
