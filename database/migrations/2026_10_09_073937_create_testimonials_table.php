<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('testimonials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('space_id')->constrained('spaces')->cascadeOnDelete();

            // Locked customer fields (NOT EAV — these are columns, not config rows).
            // Name, Email, Address are always required and never public.
            $table->string('name', 120);
            $table->string('email', 180);
            $table->string('address', 255);

            // Three optional columns toggled per Space via spaces.field_config.
            $table->string('company_name', 160)->nullable();
            $table->string('social_url', 255)->nullable();
            $table->string('profile_photo', 255)->nullable();

            // Free-text content, max 2000 chars enforced at the form layer.
            $table->text('testimonial');

            // Optional rating (1..5) when spaces.rating_enabled = true.
            $table->unsignedTinyInteger('rating')->nullable();

            // Consent (audit trail — see config/consent.php).
            $table->boolean('consent_given')->default(false);
            $table->timestamp('consented_at')->nullable();
            $table->string('consent_text_version', 40)->nullable();

            // Visibility flags (see data-model §3.3).
            $table->boolean('is_favorite')->default(false);
            $table->boolean('is_wall_of_love')->default(false);
            $table->boolean('is_hidden')->default(false);

            // Domain timestamp, distinct from created_at. Drives the
            // collection graph and the embed ORDER BY.
            $table->timestamp('submitted_at')->nullable();

            $table->softDeletes();
            $table->timestamps();

            // `idx_space_live` and `idx_respondents` are not created here —
            // they include `deleted_at` which is only present after
            // `softDeletes()`, and the ordering on `idx_public` requires
            // `is_public`, which is added below by the ALTER. They go in
            // a follow-up CREATE INDEX block to keep the column order
            // explicit (the data-model is the source of truth for the
            // exact column set).
        });

        // `is_public` STORED generated column — the §8 four-condition rule
        // lives in the schema, not in four hand-written code paths.
        // HARD RULE 3: if MySQL refuses this expression (e.g. older MySQL,
        // strict mode, etc.), do NOT improvise — surface the exact error.
        DB::statement(<<<'SQL'
            ALTER TABLE testimonials
            ADD COLUMN is_public TINYINT(1)
            GENERATED ALWAYS AS (
                CASE WHEN consent_given = 1
                      AND is_wall_of_love = 1
                      AND is_hidden = 0
                      AND deleted_at IS NULL
                     THEN 1 ELSE 0 END
            ) STORED
        SQL);

        // Indexes from data-model §3.3:
        //   idx_public       (space_id, is_public, is_favorite, submitted_at)
        //   idx_space_live   (space_id, deleted_at,    submitted_at)
        //   idx_respondents  (space_id, deleted_at,    email)
        DB::statement('CREATE INDEX idx_public       ON testimonials (space_id, is_public, is_favorite, submitted_at)');
        DB::statement('CREATE INDEX idx_space_live   ON testimonials (space_id, deleted_at, submitted_at)');
        DB::statement('CREATE INDEX idx_respondents  ON testimonials (space_id, deleted_at, email)');
    }

    public function down(): void
    {
        Schema::dropIfExists('testimonials');
    }
};