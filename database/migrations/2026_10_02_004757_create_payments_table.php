<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Every payment the resort has actually received, one row per payment.
 *
 * The reservation tables only know what a guest was asked to pay and, for PayMongo, when
 * the downpayment arrived. A booking confirmed by hand or a balance taken at the front
 * desk left no record of when the money came in, so there was no way to say what was
 * collected today or this month. This is that record, and the Sales page reads from it.
 */
return new class extends Migration
{
    /**
     * The three reservation tables, the model each belongs to, and the column holding
     * what the guest paid up front.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const TABLES = [
        'bookings' => ['App\\Models\\Booking', 'downpayment'],
        'room_bookings' => ['App\\Models\\RoomBooking', 'amount_paid'],
        'catering_orders' => ['App\\Models\\CateringOrder', 'downpayment'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->morphs('payable');
            $table->string('kind');
            $table->unsignedInteger('amount');
            $table->string('method')->nullable();
            $table->string('reference')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('received_at')->index();
            $table->timestamps();
        });

        $this->backfill();
    }

    /**
     * Write down the payments already on the books, as near to when they arrived as the
     * old columns can say.
     *
     * Confirmed and completed reservations had their downpayment verified: when PayMongo
     * took it that is `paid_at`, and when staff confirmed it by hand the booking's own
     * creation is the best date left. A settled balance arrived at `balance_settled_at`.
     */
    private function backfill(): void
    {
        $now = now();

        foreach (self::TABLES as $table => [$type, $paidColumn]) {
            DB::table($table)
                ->whereIn('status', ['confirmed', 'completed'])
                ->orderBy('id')
                ->each(function (object $reservation) use ($type, $paidColumn, $now) {
                    $rows = [];

                    if ($reservation->{$paidColumn} > 0) {
                        $rows[] = [
                            'payable_type' => $type,
                            'payable_id' => $reservation->id,
                            'kind' => 'downpayment',
                            'amount' => $reservation->{$paidColumn},
                            'method' => $reservation->payment_method,
                            'reference' => $reservation->payment_reference,
                            'received_at' => $reservation->paid_at ?? $reservation->created_at ?? $now,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($reservation->balance_settled_at !== null && $reservation->balance > 0) {
                        $rows[] = [
                            'payable_type' => $type,
                            'payable_id' => $reservation->id,
                            'kind' => 'balance',
                            'amount' => $reservation->balance,
                            'method' => null,
                            'reference' => null,
                            'received_at' => $reservation->balance_settled_at,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    if ($rows !== []) {
                        DB::table('payments')->insert($rows);
                    }
                });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
