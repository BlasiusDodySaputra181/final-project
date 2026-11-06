<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('destructions', function (Blueprint $table) {
            $table->id();
            $table->text('description')->nullable();
            $table->string('agreement')->index();
            $table->foreignId('final_project_id')->nullable()->constrained('final__projects')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('destructions');
    }
};
