<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What the payment gateway knows about each reservation.
 *
 * Bookings used to be paid for by sending GCash by hand and clicking "I've sent it", which
 * left nothing behind to check a payment against. They now go through PayMongo Checkout,
 * which hands back a payment reference — the thing the resort asked for.
 *
 * `status` stays the reservation's own lifecycle and these track the money, because the
 * two genuinely differ: a booking whose checkout is still open and one whose card was
 * declined are both Pending, and staff have to tell them apart.
 */
return new class extends Migration
{
    /**
     * The three reservation tables, and the column each one's payment columns follow.
     *
     * @var array<string, string>
     */
    private const TABLES = [
        'bookings' => 'balance_settled_at',
        'room_bookings' => 'balance_settled_at',
        'catering_orders' => 'balance_settled_at',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $table => $after) {
            Schema::table($table, function (Blueprint $blueprint) use ($after) {
                $blueprint->string('payment_provider')->nullable()->after($after);
                $blueprint->string('payment_status')->default('unpaid')->after('payment_provider');

                // The checkout session the guest was sent to, and what came back from it.
                $blueprint->string('payment_session_id')->nullable()->after('payment_status');
                $blueprint->string('payment_intent_id')->nullable()->after('payment_session_id');
                $blueprint->string('payment_reference')->nullable()->after('payment_intent_id');
                $blueprint->string('payment_method')->nullable()->after('payment_reference');

                // What actually arrived, as against what was asked for. They can differ,
                // and a booking is not confirmed until they match.
                $blueprint->unsignedInteger('paid_amount')->nullable()->after('payment_method');
                $blueprint->timestamp('paid_at')->nullable()->after('paid_amount');

                // When an unpaid booking stops holding its dates.
                $blueprint->timestamp('payment_expires_at')->nullable()->after('paid_at');

                $blueprint->index('payment_session_id');
                $blueprint->index('payment_reference');
                $blueprint->index(['payment_status', 'payment_expires_at']);
            });
        }

        // Anything already on the books was taken by hand and is not PayMongo's to
        // explain, so it is marked paid rather than left looking like it still owes a
        // checkout. What it actually paid is the deposit already recorded against it.
        DB::table('bookings')->update(['payment_status' => 'paid', 'paid_amount' => DB::raw('downpayment')]);
        DB::table('catering_orders')->update(['payment_status' => 'paid', 'paid_amount' => DB::raw('downpayment')]);
        DB::table('room_bookings')->update(['payment_status' => 'paid', 'paid_amount' => DB::raw('amount_paid')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (array_keys(self::TABLES) as $table) {
            Schema::table($table, function (Blueprint $blueprint) use ($table) {
                $blueprint->dropIndex($table.'_payment_session_id_index');
                $blueprint->dropIndex($table.'_payment_reference_index');
                $blueprint->dropIndex($table.'_payment_status_payment_expires_at_index');

                $blueprint->dropColumn([
                    'payment_provider', 'payment_status', 'payment_session_id', 'payment_intent_id',
                    'payment_reference', 'payment_method', 'paid_amount', 'paid_at', 'payment_expires_at',
                ]);
            });
        }
    }
};
