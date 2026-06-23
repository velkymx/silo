<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('title')->nullable()->after('email');
            $table->string('department')->nullable()->after('title');
            $table->string('phone')->nullable()->after('department');
            $table->string('location')->nullable()->after('phone');
            $table->text('bio')->nullable()->after('location');
            $table->date('start_date')->nullable()->after('bio');
            // "Reports to" — self-referential, nulled if the manager is removed.
            $table->foreignId('manager_id')->nullable()->after('start_date')
                ->constrained('users')->nullOnDelete();

            $table->index('department');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('manager_id');
            $table->dropColumn(['title', 'department', 'phone', 'location', 'bio', 'start_date']);
        });
    }
};
