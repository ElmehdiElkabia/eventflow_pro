<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // Add missing columns
            $table->foreignId('event_id')->nullable()->after('ticket_id')->constrained()->onDelete('cascade');
            $table->integer('quantity')->default(1)->after('event_id');
            $table->json('payment_data')->nullable()->after('transaction_ref');
            $table->timestamp('refunded_at')->nullable()->after('payment_data');
        });

        // Update the status enum to use 'completed' instead of 'success'
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending'");
        
        // Update existing 'success' status to 'completed' (if any exist)
        DB::table('transactions')
            ->where('status', 'success')
            ->update(['status' => 'completed']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert status enum back to original
        DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'success', 'failed', 'refunded') DEFAULT 'pending'");
        
        // Update 'completed' back to 'success'
        DB::table('transactions')
            ->where('status', 'completed')
            ->update(['status' => 'success']);

        Schema::table('transactions', function (Blueprint $table) {
            // Remove added columns
            $table->dropForeign(['event_id']);
            $table->dropColumn(['event_id', 'quantity', 'payment_data', 'refunded_at']);
        });
    }
};