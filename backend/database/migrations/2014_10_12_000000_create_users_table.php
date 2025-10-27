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
		Schema::create('users', function (Blueprint $table) {
			$table->id();
			$table->string('name', 100);
			$table->string('email', 150)->unique();
			$table->string('password', 255);
			$table->enum('role', ['super_admin', 'organizer', 'user'])->default('user');
			$table->string('phone', 20)->nullable();
			$table->string('avatar', 255)->nullable();
			$table->enum('status', ['active', 'inactive', 'banned'])->default('active');
			$table->timestamp('email_verified_at')->nullable();
			$table->rememberToken();
			$table->timestamps();
		});
	}

	/**
	 * Reverse the migrations.
	 */
	public function down(): void
	{
		Schema::dropIfExists('users');
	}
};
