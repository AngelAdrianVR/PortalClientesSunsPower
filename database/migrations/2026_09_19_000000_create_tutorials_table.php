<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tutoriales (videos o archivos) que el portal muestra en la sección
     * "Tutoriales" del menú lateral.
     *
     * Se administran desde un panel oculto (solo se entra escribiendo la URL
     * con la llave `TUTORIALS_ADMIN_KEY`) y son GLOBALES para todos los
     * clientes, no pertenecen a un cliente en particular.
     *
     * El archivo se guarda en el disco `public` del portal
     * (storage/app/public/tutorials) y se sirve como estático en
     * /storage/tutorials/... para que el navegador pueda pedirlo por rangos
     * (el video se reproduce sin descargarlo por completo).
     */
    public function up(): void
    {
        Schema::create('tutorials', function (Blueprint $table) {
            $table->id();

            $table->string('title');
            $table->text('description')->nullable();

            // `file` = archivo subido al portal · `link` = URL externa (YouTube,
            // Vimeo o un enlace directo a un video/PDF en un CDN).
            $table->string('kind', 10)->default('file');

            // Solo para `kind = file`.
            $table->string('disk', 30)->nullable();
            $table->string('path')->nullable();
            $table->string('original_name')->nullable();
            $table->string('mime', 120)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('poster_path')->nullable();

            // Solo para `kind = link`.
            $table->string('url', 2048)->nullable();

            // Orden manual de aparición (menor primero) y visibilidad.
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tutorials');
    }
};
