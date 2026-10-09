<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embed_configurations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('spaces')->cascadeOnDelete();

            // 0 or 1 per Space. The row is optional; with no saved row,
            // code defaults apply (data-model §3.4).
            $table->enum('layout', ['masonry', 'carousel'])->default('masonry');
            $table->boolean('dark_mode')->default(false);
            $table->boolean('animation_enabled')->default(true);
            $table->char('background_color', 7)->nullable(); // #RRGGBB
            $table->unsignedSmallInteger('item_limit')->default(12); // capped at 50 server-side
            $table->boolean('show_rating')->default(true);

            $table->timestamps();

            $table->unique('space_id', 'uniq_embed_space');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embed_configurations');
    }
};