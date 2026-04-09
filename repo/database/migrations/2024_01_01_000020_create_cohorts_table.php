<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cohorts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('cohort_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cohort_id')->constrained()->cascadeOnDelete();
            $table->foreignId('security_exercise_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->string('status')->default('assigned');
            $table->timestamps();

            $table->unique(['cohort_id', 'security_exercise_id', 'learner_id'], 'cohort_exercise_learner_unique');
        });

        // Add foreign key for cohort_id on exercise_attempts
        Schema::table('exercise_attempts', function (Blueprint $table) {
            $table->foreign('cohort_id')->references('id')->on('cohorts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('exercise_attempts', function (Blueprint $table) {
            $table->dropForeign(['cohort_id']);
        });
        Schema::dropIfExists('cohort_assignments');
        Schema::dropIfExists('cohorts');
    }
};
