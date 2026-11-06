<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_grants', function (Blueprint $table) {
            $table->id();
            $table->string('book_title')->index();
            $table->year('book_year')->index();
            $table->string('author')->index();
            $table->string('membership_registered')->nullable();
            $table->foreignId('final_project_id')->nullable()->constrained('final__projects')->nullOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_grants');
    }
};
