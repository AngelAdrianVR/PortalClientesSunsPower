<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No-op: la tabla `media` (Spatie Media Library) ya existe en la BD
     * compartida porque la crea el ERP sunsPowerMX (migración `2025_12_21_204759`).
     */
    public function up(): void
    {
        if (! Schema::hasTable('media')) {
            Schema::create('media', function ($table) {
                $table->id();
                $table->morphs('model');
                $table->uuid()->nullable()->unique();
                $table->string('collection_name');
                $table->string('name');
                $table->string('file_name');
                $table->string('mime_type')->nullable();
                $table->string('disk');
                $table->string('conversions_disk')->nullable();
                $table->unsignedBigInteger('size');
                $table->json('manipulations');
                $table->json('custom_properties');
                $table->json('generated_conversions');
                $table->json('responsive_images');
                $table->unsignedInteger('order_column')->nullable()->index();
                $table->nullableTimestamps();
            });
        }
    }

    public function down(): void
    {
        // No-op: no eliminar tablas administradas por el ERP.
    }
};
