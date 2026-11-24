<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            // Product cost at the moment of sale, so later price changes
            // don't rewrite past profit in reports.
            $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
        });

        // Existing sale lines: use the product's current cost.
        DB::table('bill_items')
            ->whereNotNull('product_id')
            ->orderBy('id')
            ->each(function ($item) {
                $cost = DB::table('products')->where('id', $item->product_id)->value('initial_price');
                DB::table('bill_items')->where('id', $item->id)->update(['unit_cost' => $cost ?? 0]);
            });
    }

    public function down(): void
    {
        Schema::table('bill_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
