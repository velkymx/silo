<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The engine becomes the platform's event backbone. We rename the
        // RSS-specific tables so the namespace stops leaking, then add
        // columns that every future event source will need.
        Schema::rename('rss_rules', 'automation_rules');
        Schema::rename('rss_rule_executions', 'automation_rule_executions');

        Schema::table('automation_rules', function (Blueprint $table) {
            // scope: 'personal' (user_id required) | 'team' (group-bound) | 'system' (user_id null)
            $table->string('scope', 16)->default('personal')->after('user_id');
            $table->foreignId('group_id')->nullable()->after('user_id')->constrained('groups')->nullOnDelete();
            $table->string('event_version', 8)->default('1')->after('trigger_event');
            $table->json('event_match')->nullable()->after('trigger_event'); // optional dotted-match / wildcard
            $table->index(['scope', 'enabled', 'trigger_event']);
        });

        Schema::table('automation_rule_executions', function (Blueprint $table) {
            $table->string('event_version', 8)->default('1')->after('event_type');
            $table->timestamp('occurred_at')->nullable()->after('event_type');
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('automation_rule_executions', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'created_at']);
            $table->dropColumn(['event_version', 'occurred_at']);
        });
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropIndex(['scope', 'enabled', 'trigger_event']);
            $table->dropForeign(['group_id']);
            $table->dropColumn(['scope', 'group_id', 'event_version', 'event_match']);
        });
        Schema::rename('automation_rule_executions', 'rss_rule_executions');
        Schema::rename('automation_rules', 'rss_rules');
    }
};
