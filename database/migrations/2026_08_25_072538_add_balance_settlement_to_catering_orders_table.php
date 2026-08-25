<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('catering_orders', function (Blueprint $table) {
            $table->timestamp('balance_settled_at')->nullable()->after('balance');
            $table->text('admin_note')->nullable()->after('status');
        });

        // A package must never take its orders' payment history down with it.
        Schema::table('catering_orders', function (Blueprint $table) {
            $table->dropForeign(['catering_package_id']);
            $table->foreign('catering_package_id')->references('id')->on('catering_packages')->restrictOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('catering_orders', function (Blueprint $table) {
            $table->dropForeign(['catering_package_id']);
            $table->foreign('catering_package_id')->references('id')->on('catering_packages')->cascadeOnDelete();
        });

        Schema::table('catering_orders', function (Blueprint $table) {
            $table->dropColumn(['balance_settled_at', 'admin_note']);
        });
    }
};
