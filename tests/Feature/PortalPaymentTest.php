<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\ServiceOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeClientWithOrder(): array
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

        return [$client, $order];
    }

    public function test_client_can_register_abono_with_proof(): void
    {
        Storage::fake('erp_media');

        [$client, $order] = $this->makeClientWithOrder();

        $response = $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $order->id,
            'amount' => 1500,
            'payment_date' => now()->format('Y-m-d'),
            'method' => 'Transferencia',
            'reference' => 'REF-123',
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('portal_payments', [
            'client_id' => $client->id,
            'service_order_id' => $order->id,
            'amount' => 1500,
            'status' => 'En revisión',
        ]);
    }

    public function test_abono_exceeding_balance_is_rejected(): void
    {
        Storage::fake('erp_media');

        [$client, $order] = $this->makeClientWithOrder();

        $response = $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $order->id,
            'amount' => 999999,
            'payment_date' => now()->format('Y-m-d'),
            'method' => 'Transferencia',
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('portal_payments', 0);
    }

    public function test_client_cannot_abonar_other_clients_order(): void
    {
        [$client] = $this->makeClientWithOrder();

        $otherClient = Client::create([
            'name' => 'Otro Cliente',
            'tax_id' => 'ABCD123456XYZ',
        ]);

        $otherOrder = ServiceOrder::create([
            'client_id' => $otherClient->id,
            'total_amount' => 5000,
            'status' => 'Aceptado',
        ]);

        $response = $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $otherOrder->id,
            'amount' => 100,
            'payment_date' => now()->format('Y-m-d'),
            'method' => 'Transferencia',
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ]);

        $response->assertNotFound();
        $this->assertDatabaseCount('portal_payments', 0);
    }
}
