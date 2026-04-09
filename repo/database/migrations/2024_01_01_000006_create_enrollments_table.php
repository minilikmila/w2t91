<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->string('program_name');
            $table->string('status')->default('draft');
            $table->string('previous_status')->nullable();
            $table->integer('current_approval_level')->default(0);
            $table->integer('max_approval_levels')->default(1);
            $table->json('workflow_metadata')->nullable();
            $table->boolean('requires_guardian_approval')->default(false);
            $table->string('reason_code')->nullable();
            $table->text('notes')->nullable();
            $table->decimal('payment_amount', 10, 2)->nullable();
            $table->boolean('payment_received')->default(false);
            $table->timestamp('refund_cutoff_at')->nullable();
            $table->timestamp('enrolled_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->unsignedBigInteger('last_actor_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('last_actor_id')->references('id')->on('users')->nullOnDelete();
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('enrollments');
    }
};
