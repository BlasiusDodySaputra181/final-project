<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('final__projects', ['final_project_type_id', 'id'], 'fp_type_id_index');
        $this->addIndex('final__projects', ['nim', 'id'], 'fp_nim_id_index');

        if (! $this->supportsFullText()) {
            return;
        }

        $this->ensureFinalProjectFullText();
        $this->addFullText(
            'visitors',
            ['nim', 'student_name', 'program_study', 'needs'],
            'visitors_fulltext_search'
        );
    }

    public function down(): void
    {
        $this->dropIndex('final__projects', 'fp_type_id_index');
        $this->dropIndex('final__projects', 'fp_nim_id_index');

        if (! $this->supportsFullText()) {
            return;
        }

        $this->dropFullText('final__projects', 'fp_search_fulltext');
        $this->dropFullText('visitors', 'visitors_fulltext_search');
    }

    private function supportsFullText(): bool
    {
        return in_array(Schema::getConnection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    /**
     * @param  list<string>  $columns
     */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->index($columns, $name);
        });
    }

    private function ensureFinalProjectFullText(): void
    {
        if (Schema::hasIndex('final__projects', 'fp_search_fulltext')) {
            return;
        }

        if (Schema::hasIndex('final__projects', 'fp_fulltext_search')) {
            Schema::table('final__projects', function (Blueprint $blueprint) {
                $blueprint->renameIndex('fp_fulltext_search', 'fp_search_fulltext');
            });

            return;
        }

        $this->addFullText(
            'final__projects',
            ['nim', 'student_name', 'final_project_title', 'program_study'],
            'fp_search_fulltext'
        );
    }

    /**
     * @param  list<string>  $columns
     */
    private function addFullText(string $table, array $columns, string $name): void
    {
        if (Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $name) {
            $blueprint->fullText($columns, $name);
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

    private function dropFullText(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name) {
            $blueprint->dropFullText($name);
        });
    }
};