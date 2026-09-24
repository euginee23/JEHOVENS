<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The exact days a catering order is served on.
 *
 * Catering never closes a date off, so these rows do not guard availability the way the
 * hall and room ones do. They are what the order is priced and billed against: the head
 * count is served, and charged, on every day listed here.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('catering_order_dates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catering_order_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->timestamps();

            $table->unique(['catering_order_id', 'date']);
            $table->index('date');
        });

        $this->backfill();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catering_order_dates');
    }

    /**
     * List out the days every existing order already covers.
     */
    protected function backfill(): void
    {
        DB::table('catering_orders')
            ->orderBy('id')
            ->select(['id', 'start_date', 'end_date'])
            ->chunkById(500, function ($orders) {
                $rows = [];
                $now = now();

                foreach ($orders as $order) {
                    $cursor = CarbonImmutable::parse($order->start_date)->startOfDay();
                    $last = CarbonImmutable::parse($order->end_date)->startOfDay();

                    while ($cursor->lte($last)) {
                        $rows[] = [
                            'catering_order_id' => $order->id,
                            'date' => $cursor->toDateString(),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $cursor = $cursor->addDay();
                    }
                }

                if ($rows !== []) {
                    DB::table('catering_order_dates')->insert($rows);
                }
            });
    }
};
