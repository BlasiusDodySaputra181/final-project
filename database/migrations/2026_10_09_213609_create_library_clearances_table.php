<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_clearances', function (Blueprint $table) {
            $table->id();
            $table->string('email')->index();
            $table->string('phone_number');
            $table->year('graduation_year')->index();
            $table->unsignedBigInteger('semester_graduated');
            $table->string('membership_registered')->nullable();
            $table->foreignId('final_project_id')->nullable()->constrained('final__projects')->nullOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_clearances');
    }
};
