<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Crea versiones MÍNIMAS de las tablas del ERP solo si NO existen.
     *
     * - En la BD compartida con el ERP (sunspowermx) estas tablas ya existen
     *   y esta migración NO hace nada.
     * - En entornos frescos (BD de pruebas SQLite en memoria) permite que las
     *   migraciones del portal con FKs hacia clients/service_orders/branches
     *   puedan ejecutarse y que las pruebas corran sin el esquema del ERP.
     *
     * down() no elimina nada para no afectar jamás tablas del ERP.
     */
    public function up(): void
    {
        if (! Schema::hasTable('branches')) {
            Schema::create('branches', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('clients')) {
            Schema::create('clients', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('branch_id')->nullable();
                $table->string('name');
                $table->string('contact_person')->nullable();
                $table->string('tax_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('service_orders')) {
            Schema::create('service_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->string('service_number')->nullable();
                $table->string('status')->nullable();
                $table->decimal('total_amount', 12, 2)->default(0);
                $table->string('payment_method')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('contacts')) {
            Schema::create('contacts', function (Blueprint $table) {
                $table->id();
                $table->morphs('contactable');
                $table->string('name')->nullable();
                $table->string('email')->nullable();
                $table->string('phone')->nullable();
                $table->boolean('is_primary')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payments')) {
            Schema::create('payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('client_id')->nullable();
                $table->unsignedBigInteger('service_order_id')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->decimal('interest_amount', 12, 2)->default(0);
                $table->date('payment_date')->nullable();
                $table->string('method')->nullable();
                $table->string('reference')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('payment_installments')) {
            Schema::create('payment_installments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('service_order_id')->nullable();
                $table->integer('installment_number')->default(1);
                $table->string('label')->nullable();
                $table->date('projected_date')->nullable();
                $table->decimal('amount', 12, 2)->default(0);
                $table->boolean('apply_interest')->default(true);
                $table->string('status')->default('pending');
                $table->decimal('paid_amount', 12, 2)->default(0);
                $table->date('paid_date')->nullable();
                $table->unsignedBigInteger('payment_id')->nullable();
                $table->timestamps();
            });
        }

        // En la BD del ERP, `users` incluye rfc y phone; en BD frescas de
        // prueba no existen, así que se agregan si faltan.
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'rfc')) {
                $table->string('rfc', 13)->nullable();
            }

            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No-op: nunca eliminar tablas del ERP.
    }
};
