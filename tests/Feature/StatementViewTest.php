<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentInstallment;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class StatementViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_statement_lists_installment_statuses_and_payments_oldest_first(): void
    {
        $client = Client::create([
            'name' => 'Cliente Demo',
            'tax_id' => 'XAXX010101000',
        ]);

        $order = ServiceOrder::create([
            'client_id' => $client->id,
            'total_amount' => 10000,
            'status' => 'Aceptado',
            'service_number' => 'S-001',
        ]);

        // Una cuota por estatus: pagada, vencida, por vencer (≤7 días) y pendiente.
        foreach ([
            ['installment_number' => 1, 'label' => 'Pagada', 'projected_date' => now()->subDays(40), 'status' => 'paid'],
            ['installment_number' => 2, 'label' => 'Vencida', 'projected_date' => now()->subDays(3), 'status' => 'pending'],
            ['installment_number' => 3, 'label' => 'Por vencer', 'projected_date' => now()->addDays(5), 'status' => 'pending'],
            ['installment_number' => 4, 'label' => 'Lejana', 'projected_date' => now()->addDays(20), 'status' => 'pending'],
        ] as $data) {
            PaymentInstallment::create([
                'service_order_id' => $order->id,
                'amount' => 1000,
                'projected_date' => $data['projected_date']->format('Y-m-d'),
                'installment_number' => $data['installment_number'],
                'label' => $data['label'],
                'status' => $data['status'],
            ]);
        }

        // Pagos capturados en orden invertido: el estado de cuenta debe ordenarlos
        // del más antiguo al más reciente.
        $oldDate = now()->subMonths(2)->format('Y-m-d');
        $midDate = now()->subMonth()->format('Y-m-d');
        $newDate = now()->format('Y-m-d');

        foreach ([$newDate, $oldDate, $midDate] as $index => $date) {
            Payment::create([
                'client_id' => $client->id,
                'service_order_id' => $order->id,
                'amount' => 500 + $index,
                'payment_date' => $date,
                'method' => 'Transferencia',
                'reference' => 'REF-'.$index,
            ]);
        }

        $response = $this->actingAs($client, 'portal')->get(route('statement.view'));

        $response->assertOk();
        $response->assertInertia(fn (AssertableInertia $page) => $page
            ->component('EstadoCuenta')
            ->where('services.0.installments.0.status', 'paid')
            ->where('services.0.installments.1.status', 'overdue')
            ->where('services.0.installments.2.status', 'due_soon')
            ->where('services.0.installments.3.status', 'pending')
            ->where('services.0.payments.0.payment_date', $oldDate)
            ->where('services.0.payments.1.payment_date', $midDate)
            ->where('services.0.payments.2.payment_date', $newDate)
        );
    }
}
