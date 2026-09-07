<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina `users.client_id` si llegó a existir en la BD compartida.
     *
     * El portal de clientes NO debe ligar clientes dentro de la tabla `users`
     * (esa tabla es de empleados del ERP). La identidad del portal es la
     * tabla `clients`.
     */
    public function up(): void
    {
        if (Schema::hasColumn('users', 'client_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropConstrainedForeignId('client_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'client_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('client_id')->nullable()->constrained('clients')->nullOnDelete();
            });
        }
    }
};
