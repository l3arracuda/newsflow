<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('key', 120)->unique();
            $table->string('base_url', 700);
            $table->string('listing_url', 700);
            $table->string('adapter', 120);
            $table->boolean('is_active')->default(true);
            $table->json('config')->nullable();
            $table->timestamps();
        });

        Schema::create('articles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_id')->constrained()->restrictOnDelete();
            $table->string('source_external_id', 191)->nullable();
            $table->string('source_url', 700);
            $table->string('canonical_url', 700)->nullable();
            $table->string('title');
            $table->timestamp('source_published_at')->nullable();
            $table->timestamp('discovered_at')->useCurrent();
            $table->char('content_hash', 64)->nullable();
            $table->string('status', 32)->default('discovered');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['source_id', 'source_url']);
            $table->unique(['source_id', 'source_external_id']);
            $table->index(['status', 'discovered_at']);
            $table->index(['source_id', 'source_published_at']);
            $table->index('content_hash');
        });

        Schema::create('article_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->text('normalized_excerpt');
            $table->timestamp('fetched_at');
            $table->char('checksum', 64);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'checksum']);
            $table->index(['article_id', 'fetched_at']);
        });

        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->nullable()->constrained()->nullOnDelete();
            $table->string('run_type', 60);
            $table->string('status', 32)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['article_id', 'status']);
            $table->index(['run_type', 'status']);
        });

        Schema::create('workflow_step_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_run_id')->constrained()->cascadeOnDelete();
            $table->string('step_key', 100);
            $table->string('name', 150);
            $table->string('status', 32)->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->text('error_summary')->nullable();
            $table->unsignedSmallInteger('attempt')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['workflow_run_id', 'step_key', 'attempt']);
            $table->index(['workflow_run_id', 'status']);
        });

        Schema::create('prompt_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key', 120);
            $table->string('name');
            $table->unsignedSmallInteger('version')->default(1);
            $table->longText('template');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['key', 'version']);
            $table->index(['key', 'is_active']);
        });

        Schema::create('generated_posts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('article_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('status', 32)->default('draft');
            $table->longText('draft_text');
            $table->string('source_attribution', 255);
            $table->string('source_url', 700);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['article_id', 'version']);
            $table->index(['status', 'updated_at']);
        });

        Schema::create('generated_assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_post_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('provider', 80);
            $table->string('disk', 80)->default('public');
            $table->string('path', 1024);
            $table->string('mime_type', 120)->nullable();
            $table->char('content_hash', 64)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['generated_post_id', 'version']);
            $table->index('content_hash');
        });

        Schema::create('review_decisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('decision', 32);
            $table->text('note')->nullable();
            $table->timestamp('decided_at')->useCurrent();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['generated_post_id', 'decided_at']);
            $table->index(['user_id', 'decided_at']);
        });

        Schema::create('publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('generated_post_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 60);
            $table->string('provider', 80);
            $table->string('external_post_id', 191)->nullable();
            $table->string('external_url', 1000)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->string('idempotency_key', 120)->unique();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'external_post_id']);
            $table->index(['channel', 'status']);
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('actor_type', 32)->default('system');
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 120);
            $table->string('entity_type', 191);
            $table->unsignedBigInteger('entity_id');
            $table->json('before_state')->nullable();
            $table->json('after_state')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['entity_type', 'entity_id']);
            $table->index(['actor_type', 'actor_id', 'created_at']);
            $table->index(['event', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('publications');
        Schema::dropIfExists('review_decisions');
        Schema::dropIfExists('generated_assets');
        Schema::dropIfExists('generated_posts');
        Schema::dropIfExists('prompt_templates');
        Schema::dropIfExists('workflow_step_runs');
        Schema::dropIfExists('workflow_runs');
        Schema::dropIfExists('article_snapshots');
        Schema::dropIfExists('articles');
        Schema::dropIfExists('sources');
    }
};
