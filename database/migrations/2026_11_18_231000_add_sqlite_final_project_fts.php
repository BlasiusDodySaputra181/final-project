<?php

use App\Support\ProjectSearchIndex;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        ProjectSearchIndex::install($connection->getName());
    }

    public function down(): void
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() !== 'sqlite') {
            return;
        }

        ProjectSearchIndex::drop($connection->getName());
    }
};
