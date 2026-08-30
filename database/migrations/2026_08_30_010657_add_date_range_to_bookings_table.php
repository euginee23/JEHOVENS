<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The index created with the table, which MySQL also leans on to satisfy the
     * `hall_id` foreign key. Renaming a column leaves the index name untouched, so the
     * old name is still how it has to be dropped.
     */
    private const OLD_INDEX = 'bookings_hall_id_booking_date_index';

    private const NEW_INDEX = ['hall_id', 'start_date', 'end_date'];

    /**
     * Run the migrations.
     *
     * A hall event may now run over consecutive days. `booking_date` becomes the first
     * day of that range so halls and catering orders name their dates the same way,
     * and `hours` keeps meaning hours *per day* — the same time window is held on
     * every day of the booking.
     */
    public function up(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('booking_date', 'start_date');
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('start_date');

            // How many days the booking covers, captured at booking time alongside the
            // money columns so a later change never rewrites what the guest was charged.
            $table->unsignedSmallInteger('days')->default(1)->after('end_date');
        });

        // Every booking made before this migration ran was a single day.
        DB::table('bookings')->update(['end_date' => DB::raw('start_date')]);

        Schema::table('bookings', function (Blueprint $table) {
            $table->date('end_date')->nullable(false)->change();

            // Added before the old index goes, so `hall_id` is never left without an
            // index to lead — MySQL refuses to drop the last one a foreign key can use.
            $table->index(self::NEW_INDEX);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(self::OLD_INDEX);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->index(['hall_id', 'start_date'], self::OLD_INDEX);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->dropIndex(self::NEW_INDEX);
            $table->dropColumn(['end_date', 'days']);
        });

        Schema::table('bookings', function (Blueprint $table) {
            $table->renameColumn('start_date', 'booking_date');
        });
    }
};
