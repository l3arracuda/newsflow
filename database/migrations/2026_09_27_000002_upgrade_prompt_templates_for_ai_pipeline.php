<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->longText('system_prompt')->nullable();
            $table->longText('instruction')->nullable();
            $table->json('parameters')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
        });

        Schema::table('generated_posts', function (Blueprint $table) {
            $table->foreignId('workflow_run_id')->nullable()->constrained()->cascadeOnDelete()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('generated_posts', function (Blueprint $table) {
            $table->dropUnique(['workflow_run_id']);
            $table->dropConstrainedForeignId('workflow_run_id');
        });

        Schema::table('prompt_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn(['system_prompt', 'instruction', 'parameters']);
        });
    }
};
