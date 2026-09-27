<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('generated_assets', function (Blueprint $table) {
            $table->foreignId('workflow_run_id')->nullable()->after('generated_post_id')->constrained()->cascadeOnDelete()->unique();
            $table->string('provider_asset_id', 191)->nullable()->after('provider');
            $table->unsignedSmallInteger('width')->nullable()->after('mime_type');
            $table->unsignedSmallInteger('height')->nullable()->after('width');
            $table->unsignedSmallInteger('prompt_version')->default(1)->after('height');
            $table->longText('prompt_text')->nullable()->after('prompt_version');
            $table->string('status', 32)->default('generated')->after('prompt_text');
            $table->unique(['generated_post_id', 'version']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('generated_assets', function (Blueprint $table) {
            $table->dropUnique(['workflow_run_id']);
            $table->dropUnique(['generated_post_id', 'version']);
            $table->dropIndex(['status', 'created_at']);
            $table->dropConstrainedForeignId('workflow_run_id');
            $table->dropColumn(['provider_asset_id', 'width', 'height', 'prompt_version', 'prompt_text', 'status']);
        });
    }
};
