<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('cloudflares')) {
            return;
        }

        Schema::create('cloudflares', function (Blueprint $table) {
            $table->id();
            $table->string('link', 200);
            $table->text('key');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cloudflares');
    }
};
