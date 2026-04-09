<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learner_identifiers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learner_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('value');
            $table->string('fingerprint')->nullable()->index();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_duplicate_candidate')->default(false);
            $table->unsignedBigInteger('duplicate_of_learner_id')->nullable();
            $table->string('duplicate_status')->nullable();
            $table->timestamps();

            $table->foreign('duplicate_of_learner_id')->references('id')->on('learners')->nullOnDelete();
            $table->index(['type', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learner_identifiers');
    }
};
