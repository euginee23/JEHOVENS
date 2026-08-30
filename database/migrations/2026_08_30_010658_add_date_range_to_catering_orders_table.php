<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The index created with the table, which MySQL also leans on to satisfy the
     * `catering_package_id` foreign key. Renaming a column leaves the index name
     * untouched, so the old name is still how it has to be dropped.
     */
    private const OLD_INDEX = 'catering_orders_catering_package_id_event_date_index';

    private const NEW_INDEX = ['catering_package_id', 'start_date', 'end_date'];

    /**
     * Run the migrations.
     *
     * Catering may now be ordered for a run of consecutive days — the same package and
     * head count served on each one — so `event_date` becomes the first day of a range
     * named the same way hall bookings name theirs.
     */
    public function up(): void
    {
        Schema::table('catering_orders', function (Blueprint $table) {
            $table->renameColumn('event_date', 'start_date');
        });

        Schema::table('catering_orders', function (Blueprint $table) {
            $table->date('end_date')->nullable()->after('start_date');

            // Captured at order time alongside the money columns, so a later change
            // never rewrites what the guest was charged.
            $table->unsignedSmallInteger('days')->default(1)->after('end_date');
        });

        // Every order placed before this migration ran was a single day.
        DB::table('catering_orders')->update(['end_date' => DB::raw('start_date')]);

        Schema::table('catering_orders', function (Blueprint $table) {
            $table->date('end_date')->nullable(false)->change();

            // Added before the old index goes, so `catering_package_id` is never left
            // without an index to lead — MySQL refuses to drop the last one a foreign
            // key can use.
            $table->index(self::NEW_INDEX);
        });

        Schema::table('catering_orders', function (Blueprint $table) {
            $table->dropIndex(self::OLD_INDEX);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catering_orders', function (Blueprint $table) {
            $table->index(['catering_package_id', 'start_date'], self::OLD_INDEX);
        });

        Schema::table('catering_orders', function (Blueprint $table) {
            $table->dropIndex(self::NEW_INDEX);
            $table->dropColumn(['end_date', 'days']);
        });

        Schema::table('catering_orders', function (Blueprint $table) {
            $table->renameColumn('start_date', 'event_date');
        });
    }
};
