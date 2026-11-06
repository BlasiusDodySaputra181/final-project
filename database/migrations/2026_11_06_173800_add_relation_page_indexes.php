<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('book_grants', ['final_project_id', 'id'], 'bg_project_latest_index');
        $this->addIndex('library_clearances', ['final_project_id', 'id'], 'lc_project_latest_index');
        $this->addIndex('library_clearances', ['membership_id', 'id'], 'lc_membership_latest_index');
        $this->addIndex('barcodes', ['final_project_id', 'id'], 'bc_project_latest_index');
        $this->addIndex('archives', ['final_project_id', 'id'], 'ar_project_latest_index');
        $this->addIndex('destructions', ['final_project_id', 'id'], 'ds_project_latest_index');
        $this->addIndex('reports', ['final_project_id', 'id'], 'rp_project_latest_index');
    }

    public function down(): void
    {
        $this->dropIndex('book_grants', 'bg_project_latest_index');
        $this->dropIndex('library_clearances', 'lc_project_latest_index');
        $this->dropIndex('library_clearances', 'lc_membership_latest_index');
        $this->dropIndex('barcodes', 'bc_project_latest_index');
        $this->dropIndex('archives', 'ar_project_latest_index');
        $this->dropIndex('destructions', 'ds_project_latest_index');
        $this->dropIndex('reports', 'rp_project_latest_index');
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
