<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('final__projects', function (Blueprint $table) {
            $table->id();
            $table->string('nim')->nullable()->index();
            $table->string('student_name')->nullable()->index();
            $table->text('final_project_title')->nullable();
            $table->string('program_study')->nullable()->index();
            $table->string('degree_level')->nullable()->index();
            $table->year('final_project_year')->nullable()->index();
            $table->date('submission_date')->nullable()->index();
            $table->string('final_project_file')->nullable();
            $table->string('approval_sheet')->nullable();
            $table->foreignId('final_project_type_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('study_program_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('final__projects');
    }
};
