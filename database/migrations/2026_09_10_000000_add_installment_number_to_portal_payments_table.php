<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuota proyectada (`payment_installments.installment_number`) que el cliente
     * eligió pagar al registrar el abono desde el portal.
     *
     * El ERP la usa al validar el abono para aplicarlo a la cuota correspondiente
     * de la proyección (en planes de 3, 6, 9 o 12 meses) en lugar de dejarlo como
     * un pago adicional no programado.
     */
    public function up(): void
    {
        Schema::table('portal_payments', function (Blueprint $table) {
            $table->unsignedInteger('installment_number')->nullable()->after('service_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('portal_payments', function (Blueprint $table) {
            $table->dropColumn('installment_number');
        });
    }
};
