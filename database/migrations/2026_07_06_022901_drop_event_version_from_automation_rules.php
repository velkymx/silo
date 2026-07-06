<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // v1 ships a single in-process event format. External versioning
        // would only matter if we exposed events over MCP or a public
        // API. Drop the column; reintroduce when there's a real consumer.
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->dropColumn('event_version');
        });
        Schema::table('automation_rule_executions', function (Blueprint $table) {
            $table->dropColumn('event_version');
        });
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->dropColumn('event_version');
        });
    }

    public function down(): void
    {
        Schema::table('automation_rules', function (Blueprint $table) {
            $table->string('event_version', 8)->default('1')->after('trigger_event');
        });
        Schema::table('automation_rule_executions', function (Blueprint $table) {
            $table->string('event_version', 8)->default('1')->after('event_type');
        });
        Schema::table('workflow_templates', function (Blueprint $table) {
            $table->string('event_version', 8)->default('1')->after('trigger_event');
        });
    }
};
