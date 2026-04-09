<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('learner_identifiers', function (Blueprint $table) {
            $table->dropIndex(['type', 'value']);
            $table->unique(['type', 'fingerprint'], 'learner_identifiers_type_fingerprint_unique');
        });
    }

    public function down(): void
    {
        Schema::table('learner_identifiers', function (Blueprint $table) {
            $table->dropUnique('learner_identifiers_type_fingerprint_unique');
            $table->index(['type', 'value']);
        });
    }
};
