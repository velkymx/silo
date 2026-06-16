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
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('disk')->default('local');
            $table->string('path')->nullable();
            $table->string('filename');
            $table->unsignedBigInteger('size')->default(0);
            $table->string('status')->default('pending'); // pending | ready | failed
            $table->string('compression')->nullable();     // bzip2 | deflate
            $table->text('note')->nullable();              // error or manifest summary
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
