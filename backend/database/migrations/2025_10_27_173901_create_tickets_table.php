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
		Schema::create('tickets', function (Blueprint $table) {
			$table->id();
			$table->foreignId('event_id')->constrained()->onDelete('cascade');
			$table->string('name', 100);
			$table->enum('type', ['free', 'paid', 'early_bird']);
			$table->decimal('price', 10, 2)->default(0);
			$table->integer('quantity');
			$table->integer('sold')->default(0);
			$table->string('qr_code', 255)->nullable();
			$table->dateTime('sale_start');
			$table->dateTime('sale_end');
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('tickets');
	}
};
