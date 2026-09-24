<?php

namespace App\Console\Commands;

use App\Exceptions\PayMongoException;
use App\Support\CheckoutRequest;
use App\Support\PayMongo;
use Illuminate\Console\Command;

/**
 * Ask PayMongo what this account can actually take, without making a booking.
 *
 * PayMongo does not publish the enabled methods anywhere readable, and a checkout session
 * naming a method the account lacks is created happily — the guest only discovers it on
 * the payment page, reading "No payment methods are available". This opens a throwaway
 * session so that can be found out in a terminal instead of by a paying customer.
 *
 * Nothing is charged: a checkout session is only an offer, and an unpaid one expires.
 */
class CheckPayMongoMethodsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'resort:paymongo-check
                            {--methods= : Comma-separated methods to ask for, e.g. qrph. Omit to ask for whatever the account has}
                            {--amount=100 : What to pretend to charge, in whole pesos}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Open a throwaway PayMongo checkout session to see which payment methods the account offers';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if (! PayMongo::isConfigured()) {
            $this->error('No PAYMONGO_SECRET_KEY is set, so there is nothing to ask.');

            return self::FAILURE;
        }

        $methods = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('methods')))));
        $amount = (int) $this->option('amount');

        $this->line('Key:     '.$this->keyKind());
        $this->line('Asking:  '.($methods === [] ? 'whatever the account has enabled' : implode(', ', $methods)));
        $this->line('Amount:  ₱'.number_format($amount));
        $this->newLine();

        try {
            $session = PayMongo::make()->createCheckoutSession($this->request($methods, $amount));
        } catch (PayMongoException $e) {
            $this->error('PayMongo refused the session:');
            $this->line('  '.$e->getMessage());
            $this->newLine();
            $this->line('A rejected method name means this account cannot offer it through Checkout.');

            return self::FAILURE;
        }

        $this->info('PayMongo accepted the session.');
        $this->newLine();
        $this->line('Open this and see which methods are actually offered:');
        $this->line('  '.$session->checkoutUrl);
        $this->newLine();
        $this->warn('Being accepted is not proof it is payable — a session naming a method the');
        $this->warn('account lacks is still created, and simply shows nothing to pay with.');

        return self::SUCCESS;
    }

    /**
     * A throwaway request that looks enough like a real booking to be representative.
     *
     * @param  array<int, string>  $methods
     */
    private function request(array $methods, int $amount): CheckoutRequest
    {
        $reference = 'CHECK-'.now()->format('His');

        return new CheckoutRequest(
            reference: $reference,
            description: 'Payment method check — not a booking',
            lineItemName: 'Payment method check',
            amount: $amount,
            successUrl: url('/'),
            cancelUrl: url('/'),
            methods: $methods,
            metadata: ['type' => 'check', 'reference' => $reference],
        );
    }

    /**
     * Whether the configured key is a sandbox one, since that changes what is available.
     */
    private function keyKind(): string
    {
        $key = (string) config('services.paymongo.secret_key');

        return match (true) {
            str_starts_with($key, 'sk_test_') => 'test (sandbox)',
            str_starts_with($key, 'sk_live_') => 'live',
            default => 'unrecognised — check PAYMONGO_SECRET_KEY',
        };
    }
}
