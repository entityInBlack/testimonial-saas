<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deletion_requests', function (Blueprint $table) {
            $table->id();

            // Who asked (the email is the primary identifier — it survives
            // even if the testimonial is later hard-deleted by Forget).
            $table->string('email', 180);

            // The slug the respondent typed (kept as-typed for audit).
            $table->string('space_slug', 60);

            // Nullable FKs — both go null when the row they reference is
            // hard-deleted. The dashboard joins on space_id, and the
            // forget / purge queries need the testimonial_id for the
            // 3-step order, but a deleted Space or testimonial must
            // not orphan a deletion_request.
            $table->foreignId('space_id')->nullable()->constrained('spaces')->nullOnDelete();
            $table->foreignId('testimonial_id')->nullable()->constrained('testimonials')->nullOnDelete();

            $table->enum('status', ['open', 'acted'])->default('open');
            $table->timestamp('acted_at')->nullable();
            $table->timestamps();

            $table->index(['space_id', 'status'], 'idx_deletion_space_status');
            $table->index(['email', 'status'], 'idx_deletion_email_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deletion_requests');
    }
};