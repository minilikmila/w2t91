<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('route_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained()->cascadeOnDelete();
            $table->integer('version_number');
            $table->json('waypoints');
            $table->json('prior_values')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->text('change_reason')->nullable();
            $table->timestamps();

            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->unique(['route_id', 'version_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('route_versions');
    }
};
