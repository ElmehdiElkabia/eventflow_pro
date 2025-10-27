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
		Schema::create('events', function (Blueprint $table) {
			$table->id();
			$table->foreignId('organizer_id')->constrained('users')->onDelete('cascade');
			$table->string('title', 200);
			$table->text('description');
			$table->string('category', 100);
			$table->string('location', 255);
			$table->dateTime('start_date');
			$table->dateTime('end_date');
			$table->integer('capacity');
			$table->enum('status', ['draft', 'published', 'cancelled', 'completed'])->default('draft');
			$table->string('banner', 255)->nullable();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('events');
	}
};
