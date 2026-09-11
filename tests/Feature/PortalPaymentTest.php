<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\PortalPayment;
use App\Models\ServiceOrder;
use App\Services\PortfolioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PortalPaymentTest extends TestCase
{
    use RefreshDatabase;

    private function makeClientWithOrder(?string $paymentMethod = 'Personalizado'): array
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
            'payment_method' => $paymentMethod,
        ]);

        return [$client, $order];
    }

    /** Crea la proyección de 3 mensualidades usada por las pruebas del resumen. */
    private function makeInstallments(ServiceOrder $order): void
    {
        $order->paymentInstallments()->createMany([
            ['installment_number' => 1, 'label' => 'Mensualidad 1 de 3', 'projected_date' => now()->addMonth()->format('Y-m-d'), 'amount' => 4000],
            ['installment_number' => 2, 'label' => 'Mensualidad 2 de 3', 'projected_date' => now()->addMonths(2)->format('Y-m-d'), 'amount' => 4000],
            ['installment_number' => 3, 'label' => 'Mensualidad 3 de 3', 'projected_date' => now()->addMonths(3)->format('Y-m-d'), 'amount' => 2000],
        ]);
    }

    public function test_client_can_register_abono_with_proof(): void
    {
        Storage::fake('erp_media');

        [$client, $order] = $this->makeClientWithOrder();

        $response = $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $order->id,
            'installment_number' => 2,
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
            'installment_number' => 2,
            'amount' => 1500,
            'status' => 'En revisión',
        ]);
    }

    public function test_installment_number_must_be_a_positive_integer(): void
    {
        Storage::fake('erp_media');

        [$client, $order] = $this->makeClientWithOrder();

        $response = $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $order->id,
            'installment_number' => 0,
            'amount' => 1500,
            'payment_date' => now()->format('Y-m-d'),
            'method' => 'Transferencia',
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('installment_number');
        $this->assertDatabaseCount('portal_payments', 0);
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

    public function test_abono_is_blocked_when_service_has_no_payment_plan(): void
    {
        Storage::fake('erp_media');

        [$client, $order] = $this->makeClientWithOrder(null);

        $response = $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $order->id,
            'amount' => 1500,
            'payment_date' => now()->format('Y-m-d'),
            'method' => 'Transferencia',
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ]);

        $response->assertSessionHasErrors('amount');
        $this->assertDatabaseCount('portal_payments', 0);
    }

    public function test_summary_offers_uncovered_payment_for_custom_plan_without_installments(): void
    {
        [$client, $order] = $this->makeClientWithOrder('Personalizado');

        $summary = PortfolioService::summary($client);
        $item = collect($summary['uncovered_payments'])->firstWhere('service_id', $order->id);

        $this->assertNotNull($item);
        $this->assertSame(10000.0, (float) $item['uncovered_amount']);
        $this->assertSame(0.0, (float) $item['projected_total']);
        $this->assertFalse($item['pending_review']);
        $this->assertSame('Personalizado', $item['payment_method']);
        $this->assertEmpty($summary['services_without_plan']);
    }

    public function test_summary_uncovered_amount_excludes_projected_installments(): void
    {
        [$client, $order] = $this->makeClientWithOrder('Personalizado');

        // El proveedor programó una sola cuota parcial de un saldo de 10000.
        $order->paymentInstallments()->create([
            'installment_number' => 1,
            'label' => 'Parcialidad 1',
            'projected_date' => now()->addMonth()->format('Y-m-d'),
            'amount' => 2500,
        ]);

        $item = collect(PortfolioService::summary($client)['uncovered_payments'])->firstWhere('service_id', $order->id);

        $this->assertNotNull($item);
        $this->assertSame(2500.0, (float) $item['projected_total']);
        $this->assertSame(7500.0, (float) $item['uncovered_amount']);
    }

    public function test_summary_does_not_offer_uncovered_payment_when_projection_covers_balance(): void
    {
        [$client, $order] = $this->makeClientWithOrder('Personalizado');
        $this->makeInstallments($order); // 4000 + 4000 + 2000 = 10000 (saldo total).

        $summary = PortfolioService::summary($client);

        $this->assertNull(collect($summary['uncovered_payments'])->firstWhere('service_id', $order->id));
        $this->assertCount(3, $summary['remaining_payments']);
        $this->assertFalse($summary['remaining_payments'][0]['plan_missing']);
    }

    public function test_summary_lists_services_without_payment_plan(): void
    {
        [$client, $order] = $this->makeClientWithOrder(null);

        // Aunque existan cuotas viejas, sin plan de pago no se permite pagar.
        $order->paymentInstallments()->create([
            'installment_number' => 1,
            'label' => 'Mensualidad',
            'projected_date' => now()->addMonth()->format('Y-m-d'),
            'amount' => 1000,
        ]);

        $summary = PortfolioService::summary($client);
        $item = collect($summary['services_without_plan'])->firstWhere('service_id', $order->id);

        $this->assertNotNull($item);
        $this->assertSame(10000.0, (float) $item['service_balance']);
        $this->assertTrue($summary['remaining_payments'][0]['plan_missing']);
        $this->assertNull(collect($summary['uncovered_payments'])->firstWhere('service_id', $order->id));
    }

    public function test_uncovered_payment_in_review_flags_custom_plan_row(): void
    {
        Storage::fake('erp_media');

        [$client, $order] = $this->makeClientWithOrder('Personalizado');

        $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $order->id,
            'amount' => 10000,
            'payment_date' => now()->format('Y-m-d'),
            'method' => 'Transferencia',
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $item = collect(PortfolioService::summary($client)['uncovered_payments'])->firstWhere('service_id', $order->id);

        $this->assertNotNull($item);
        $this->assertTrue($item['pending_review']);
    }

    public function test_summary_flags_installments_with_interest_disabled(): void
    {
        [$client, $order] = $this->makeClientWithOrder('Personalizado');

        $order->paymentInstallments()->create([
            'installment_number' => 1,
            'label' => 'Sin interés',
            'projected_date' => now()->subDays(20)->format('Y-m-d'),
            'amount' => 2500,
            'apply_interest' => false,
        ]);

        $order->paymentInstallments()->create([
            'installment_number' => 2,
            'label' => 'Con interés',
            'projected_date' => now()->subDays(10)->format('Y-m-d'),
            'amount' => 2500,
            'apply_interest' => true,
        ]);

        $rows = PortfolioService::summary($client)['remaining_payments'];

        $this->assertTrue($rows[0]['interest_disabled']);
        $this->assertSame(0.0, (float) $rows[0]['interest']);
        $this->assertFalse($rows[1]['interest_disabled']);
        $this->assertGreaterThan(0, (float) $rows[1]['interest']);
    }

    public function test_remaining_payment_is_flagged_pending_review_after_abono(): void
    {
        Storage::fake('erp_media');

        [$client, $order] = $this->makeClientWithOrder();

        // Proyección de 3 mensualidades.
        $this->makeInstallments($order);

        $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $order->id,
            'installment_number' => 2,
            'amount' => 4000,
            'payment_date' => now()->format('Y-m-d'),
            'method' => 'Transferencia',
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $rows = collect(PortfolioService::summary($client)['remaining_payments'])
            ->keyBy('installment_number');

        $this->assertTrue($rows[2]['pending_review']);
        $this->assertFalse($rows[1]['pending_review']);
        $this->assertFalse($rows[3]['pending_review']);
    }

    public function test_rejected_abono_shows_reason_and_unblocks_repayment(): void
    {
        Storage::fake('erp_media');

        [$client, $order] = $this->makeClientWithOrder();
        $this->makeInstallments($order);

        $this->actingAs($client, 'portal')->post(route('portal-payments.store'), [
            'service_order_id' => $order->id,
            'installment_number' => 2,
            'amount' => 4000,
            'payment_date' => now()->format('Y-m-d'),
            'method' => 'Transferencia',
            'proof' => UploadedFile::fake()->create('comprobante.pdf', 100, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        // El ERP rechaza el abono y guarda el motivo.
        $abono = PortalPayment::firstOrFail();
        $abono->update([
            'status' => PortalPayment::STATUS_REJECTED,
            'rejection_reason' => 'El comprobante no es legible.',
            'validated_at' => now(),
        ]);

        $summary = PortfolioService::summary($client);

        // La cuota vuelve a habilitar el pago (ya no queda "Pendiente de revisión").
        $rows = collect($summary['remaining_payments'])->keyBy('installment_number');
        $this->assertFalse($rows[2]['pending_review']);

        // El rechazo se muestra con su motivo y el saldo del servicio para reintentar.
        $rejected = collect($summary['rejected_abonos'])->firstWhere('id', $abono->id);

        $this->assertNotNull($rejected);
        $this->assertSame('El comprobante no es legible.', $rejected['rejection_reason']);
        $this->assertSame(2, $rejected['installment_number']);
        $this->assertGreaterThan(0, $rejected['service_balance']);
    }
}
