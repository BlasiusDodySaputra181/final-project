<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('final__projects', ['final_project_type_id', 'nim', 'id'], 'fp_type_nim_id_index');
        $this->addIndex('final__projects', ['final_project_type_id', 'student_name', 'id'], 'fp_type_name_id_index');
        $this->addIndex('final__projects', ['final_project_type_id', 'program_study', 'id'], 'fp_type_prodi_id_index');
        $this->addIndex('final__projects', ['final_project_type_id', 'final_project_year', 'id'], 'fp_type_year_id_index');
        $this->addIndex('visitors', ['supervision'], 'visitors_supervision_index');
        $this->addIndex('memberships', ['gender'], 'memberships_gender_index');
        $this->addIndex('memberships', ['place_of_birth'], 'memberships_birth_place_index');
        $this->addIndex('memberships', ['date_of_birth'], 'memberships_birth_date_index');
        $this->addIndex('library_clearances', ['phone_number'], 'lc_phone_index');
        $this->addIndex('library_clearances', ['semester_graduated'], 'lc_semester_index');
        $this->addIndex('study_programs', ['cluster'], 'study_programs_cluster_index');
    }

    public function down(): void
    {
        $this->dropIndex('final__projects', 'fp_type_nim_id_index');
        $this->dropIndex('final__projects', 'fp_type_name_id_index');
        $this->dropIndex('final__projects', 'fp_type_prodi_id_index');
        $this->dropIndex('final__projects', 'fp_type_year_id_index');
        $this->dropIndex('visitors', 'visitors_supervision_index');
        $this->dropIndex('memberships', 'memberships_gender_index');
        $this->dropIndex('memberships', 'memberships_birth_place_index');
        $this->dropIndex('memberships', 'memberships_birth_date_index');
        $this->dropIndex('library_clearances', 'lc_phone_index');
        $this->dropIndex('library_clearances', 'lc_semester_index');
        $this->dropIndex('study_programs', 'study_programs_cluster_index');
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table) || Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropIndex($name);
        });
    }
};
