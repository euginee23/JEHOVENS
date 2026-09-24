<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The exact days a room booking occupies.
 *
 * These are the days the room is held, which for an overnight stay means the nights slept
 * and not the morning the guest checks out: three nights from the 10th lists the 10th,
 * 11th and 12th, and the room is free again on the 13th.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('room_booking_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_booking_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['room_booking_id', 'date']);
            $table->index('date');
        });

        $this->backfill();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('room_booking_dates');
    }

    /**
     * List out the days every existing stay already occupies.
     */
    protected function backfill(): void
    {
        DB::table('room_bookings')
            ->orderBy('id')
            ->select(['id', 'starts_at', 'nights'])
            ->chunkById(500, function ($stays) {
                $rows = [];
                $now = now();

                foreach ($stays as $stay) {
                    $cursor = CarbonImmutable::parse($stay->starts_at)->startOfDay();

                    // A day use has no nights but still takes up the one day it runs on.
                    for ($day = 0; $day < max((int) $stay->nights, 1); $day++) {
                        $rows[] = [
                            'room_booking_id' => $stay->id,
                            'date' => $cursor->addDays($day)->toDateString(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }

                if ($rows !== []) {
                    DB::table('room_booking_dates')->insert($rows);
                }
            });
    }
};
