<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('slug', 60);
            $table->char('public_id', 12);
            $table->string('title', 160);
            $table->string('subtitle', 255)->nullable();
            $table->text('ask');
            $table->enum('theme', ['minimal_light', 'minimal_dark', 'soft_color']);
            $table->boolean('rating_enabled')->default(true);
            $table->json('field_config');
            $table->softDeletes();
            $table->timestamps();

            $table->unique('slug');
            $table->unique('public_id');
            $table->index(['user_id', 'deleted_at'], 'idx_spaces_user_deleted');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spaces');
    }
};
