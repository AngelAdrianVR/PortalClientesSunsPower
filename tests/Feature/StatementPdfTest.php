<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PaymentInstallment;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatementPdfTest extends TestCase
{
    use RefreshDatabase;

    private function makeClientWithOrderAndInstallments(): array
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

        PaymentInstallment::create([
            'service_order_id' => $order->id,
            'installment_number' => 1,
            'label' => 'Cuota 1',
            'projected_date' => now()->addDays(10),
            'amount' => 1000,
            'apply_interest' => true,
            'status' => 'pending',
        ]);

        return [$client, $order];
    }

    public function test_client_can_download_own_statement_pdf(): void
    {
        [$client, $order] = $this->makeClientWithOrderAndInstallments();

        $response = $this->actingAs($client, 'portal')
            ->get(route('services.statement', $order->id));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_client_can_download_general_statement_pdf(): void
    {
        [$client] = $this->makeClientWithOrderAndInstallments();

        $response = $this->actingAs($client, 'portal')
            ->get(route('statement.download-all'));

        $response->assertOk();
        $this->assertStringContainsString('application/pdf', $response->headers->get('content-type'));
    }

    public function test_client_cannot_download_other_clients_statement(): void
    {
        [$client] = $this->makeClientWithOrderAndInstallments();

        $otherClient = Client::create([
            'name' => 'Otro Cliente',
            'tax_id' => 'ABCD123456XYZ',
        ]);

        $otherOrder = ServiceOrder::create([
            'client_id' => $otherClient->id,
            'total_amount' => 5000,
            'status' => 'Aceptado',
        ]);

        $response = $this->actingAs($client, 'portal')
            ->get(route('services.statement', $otherOrder->id));

        $response->assertNotFound();
    }
}
