<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Abonos registrados por el cliente desde el portal.
     *
     * Quedan "En revisión" hasta que el personal del ERP valide el comprobante
     * y los convierta en un `Payment` real (pagado). Mientras estén "En revisión"
     * NO afectan el saldo del cliente.
     *
     * El comprobante se guarda con Spatie Media Library (colección `receipts`)
     * en el disco compartido `erp_media`.
     */
    public function up(): void
    {
        Schema::create('portal_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('client_id')->constrained('clients')->cascadeOnDelete();
            // Abono general (service_order_id puede ser null, igual que en payments)
            $table->foreignId('service_order_id')->nullable()->constrained('service_orders')->nullOnDelete();

            $table->decimal('amount', 12, 2);
            $table->date('payment_date');
            $table->enum('method', ['Transferencia', 'Efectivo', 'Cheque', 'Tarjeta', 'Depósito', 'Otro'])->default('Transferencia');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();

            // Flujo de validación manual
            $table->enum('status', ['En revisión', 'Completado', 'Rechazado'])->default('En revisión')->index();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('validated_at')->nullable();
            $table->text('rejection_reason')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_payments');
    }
};
