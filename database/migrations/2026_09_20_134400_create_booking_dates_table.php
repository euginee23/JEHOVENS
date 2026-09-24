<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The exact days a hall booking covers.
 *
 * `bookings.start_date` and `end_date` used to mean every day between them was booked.
 * Guests can now pick days that are not consecutive — three Saturdays, say — so the days
 * themselves are listed here and the parent keeps only their outer bounds, for the admin
 * filters and the indexes that sort on them.
 *
 * A row per date rather than a JSON column: the calendar asks "who is booked on these
 * dates", which wants an index, not a table scan.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('booking_dates', function (Blueprint $table) {
            $table->id();
            // A date has no meaning without its booking, so this one cascades.
            $table->foreignId('booking_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            // Makes booking the same day twice on one reservation impossible.
            $table->unique(['booking_id', 'date']);
            $table->index('date');
        });

        $this->backfill();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_dates');
    }

    /**
     * List out the days every existing booking already covers.
     *
     * Every row predating this migration is a consecutive run, so expanding the span is
     * lossless and leaves `days` correct as it stands.
     */
    protected function backfill(): void
    {
        DB::table('bookings')
            ->orderBy('id')
            ->select(['id', 'start_date', 'end_date'])
            ->chunkById(500, function ($bookings) {
                $rows = [];
                $now = now();

                foreach ($bookings as $booking) {
                    $cursor = CarbonImmutable::parse($booking->start_date)->startOfDay();
                    $last = CarbonImmutable::parse($booking->end_date)->startOfDay();

                    while ($cursor->lte($last)) {
                        $rows[] = [
                            'booking_id' => $booking->id,
                            'date' => $cursor->toDateString(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $cursor = $cursor->addDay();
                    }
                }

                if ($rows !== []) {
                    DB::table('booking_dates')->insert($rows);
                }
            });
    }
};
