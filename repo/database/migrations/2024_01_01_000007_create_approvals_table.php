<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enrollment_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('reviewer_id')->nullable();
            $table->integer('level');
            $table->string('status')->default('pending');
            $table->string('decision')->nullable();
            $table->text('comments')->nullable();
            $table->string('reason_code')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamps();

            $table->foreign('reviewer_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['enrollment_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('approvals');
    }
};
