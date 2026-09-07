<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * No-op: la BD es compartida con el ERP sunsPowerMX, que ya agrega las
     * columnas two_factor_* a `users` mediante su migración
     * `2025_12_21_192731_add_two_factor_columns_to_users_table.php`.
     *
     * down() tampoco debe eliminarlas porque pertenecen al ERP.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'two_factor_secret')) {
            Schema::table('users', function ($table) {
                $table->text('two_factor_secret')->nullable();
                $table->text('two_factor_recovery_codes')->nullable();
                $table->timestamp('two_factor_confirmed_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        // No-op: no eliminar columnas administradas por el ERP.
    }
};
