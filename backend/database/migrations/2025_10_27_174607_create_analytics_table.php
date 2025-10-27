<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
	/**
	 * Run the migrations.
	 */
	public function up(): void
	{
		Schema::create('analytics', function (Blueprint $table) {
			$table->id();
			$table->foreignId('event_id')->constrained()->onDelete('cascade');
			$table->integer('views')->default(0);
			$table->integer('ticket_sales')->default(0);
			$table->decimal('revenue', 10, 2)->default(0);
			$table->timestamp('updated_at')->nullable();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('analytics');
	}
};
