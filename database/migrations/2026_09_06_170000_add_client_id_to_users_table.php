<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No-op (se retiró la funcionalidad).
     *
     * La columna `users.client_id` ya NO debe existir: el portal de clientes
     * autentica directamente contra la tabla `clients` del ERP y NO mezcla
     * clientes dentro de la tabla `users` (empleados del ERP).
     *
     * Si quedó aplicada de una versión anterior, la migración
     * `2026_09_07_000000_drop_client_id_from_users_table` la elimina.
     */
    public function up(): void
    {
        // No-op.
    }

    public function down(): void
    {
        // No-op: nunca re-agregar la columna.
    }
};
