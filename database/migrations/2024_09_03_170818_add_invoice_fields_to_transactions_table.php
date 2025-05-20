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
        Schema::table('transactions', function (Blueprint $table): void {
            $table->string('invoice_number')->nullable()->after('payment_channel'); // Replace 'existing_column_name' with the column after which you want to add this field
            $table->date('invoice_due_date')->nullable()->after('invoice_number');
            $table->tinyInteger('sale_invoice_status')->nullable()->after('invoice_due_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table): void {
            //
        });
    }
};
