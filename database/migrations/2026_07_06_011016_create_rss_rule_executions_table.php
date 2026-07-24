<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_rule_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rule_id')->constrained('rss_rules')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('trigger_event', 64);
            $table->string('event_key', 191); // idempotency key
            $table->string('event_type', 64);
            $table->json('conditions_evaluated')->nullable();
            $table->json('actions_executed')->nullable();
            $table->string('status', 16); // matched|skipped|failed
            $table->text('error')->nullable();
            $table->timestamps();

            $table->unique(['rule_id', 'event_key'], 'rss_rule_exec_idempotency_unique');
            $table->index(['user_id', 'created_at']);
            $table->index(['rule_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_rule_executions');
    }
};
