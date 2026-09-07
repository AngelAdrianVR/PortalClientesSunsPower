<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PortalPayment;
use App\Models\ServiceOrder;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ServiceController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var Client $client */
        $client = $request->user('portal');

        $services = $client->serviceOrders()
            ->whereIn('status', ServiceOrder::PORTAL_STATUSES)
            ->addSelect([
                'paid_principal' => PortfolioService::paidPrincipalSubquery(),
            ])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ServiceOrder $o) => [
                'id' => $o->id,
                'service_number' => $o->service_number ?: 'Orden #'.$o->id,
                'system_type' => $o->system_type,
                'status' => $o->status,
                'start_date' => $o->start_date?->format('Y-m-d'),
                'installation_address' => $o->installation_address,
                'payment_method' => $o->payment_method,
                'total_amount' => round((float) $o->total_amount, 2),
                'balance' => round(max(0.0, (float) $o->total_amount - (float) ($o->paid_principal ?? 0)), 2),
            ]);

        return Inertia::render('Services/Index', [
            'services' => $services,
            'clientBalance' => PortfolioService::balanceFor($client),
        ]);
    }

    public function show(Request $request, ServiceOrder $serviceOrder): Response
    {
        abort_unless($serviceOrder->client_id === (int) $request->user('portal')?->id, 404);

        $serviceOrder->load([
            'client.contacts',
            'payments.media',
            'paymentInstallments',
            'contract',
        ]);

        $client = $serviceOrder->client;
        $mainContact = $client->contacts->firstWhere('is_primary', true) ?? $client->contacts->first();

        $installments = $serviceOrder->paymentInstallments
            ->sortBy(fn ($i) => $i->installment_number)
            ->values()
            ->map(fn ($i) => [
                'id' => $i->id,
                'installment_number' => $i->installment_number,
                'label' => $i->label,
                'projected_date' => $i->projected_date?->format('Y-m-d'),
                'amount' => round((float) $i->amount, 2),
                'status' => $i->current_status,
                'days_late' => $i->days_late,
                'months_of_interest' => $i->months_of_interest,
                'interest' => $i->calculateInterest(),
                'total_with_interest' => $i->total_with_interest,
                'paid_amount' => round((float) $i->paid_amount, 2),
                'paid_date' => $i->paid_date?->format('Y-m-d'),
            ]);

        $payments = $serviceOrder->payments
            ->sortByDesc(fn ($p) => $p->payment_date?->timestamp ?? 0)
            ->values()
            ->map(fn ($p) => [
                'id' => $p->id,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'amount' => round((float) $p->amount, 2),
                'interest_amount' => round((float) $p->interest_amount, 2),
                'method' => $p->method,
                'reference' => $p->reference,
                'receipt_id' => $p->getFirstMedia('receipts')?->id,
            ]);

        $abonos = PortalPayment::query()
            ->where('client_id', $client->id)
            ->where('service_order_id', $serviceOrder->id)
            ->with('media')
            ->latest()
            ->get()
            ->map(fn (PortalPayment $p) => [
                'id' => $p->id,
                'created_at' => $p->created_at?->format('Y-m-d H:i'),
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'amount' => round((float) $p->amount, 2),
                'method' => $p->method,
                'reference' => $p->reference,
                'status' => $p->status,
                'rejection_reason' => $p->rejection_reason,
                'receipt_id' => $p->getFirstMedia('receipts')?->id,
            ]);

        return Inertia::render('Services/Show', [
            'service' => [
                'id' => $serviceOrder->id,
                'service_number' => $serviceOrder->service_number ?: 'Orden #'.$serviceOrder->id,
                'system_type' => $serviceOrder->system_type,
                'rate_type' => $serviceOrder->rate_type,
                'status' => $serviceOrder->status,
                'start_date' => $serviceOrder->start_date?->format('Y-m-d'),
                'completion_date' => $serviceOrder->completion_date?->format('Y-m-d'),
                'payment_method' => $serviceOrder->payment_method,
                'down_payment' => round((float) $serviceOrder->down_payment, 2),
                'total_amount' => round((float) $serviceOrder->total_amount, 2),
                'voltage' => $serviceOrder->voltage,
                'number_of_units' => $serviceOrder->number_of_units,
                'unit_capacity' => $serviceOrder->unit_capacity,
                'total_capacity' => $serviceOrder->total_capacity,
                'meter_number' => $serviceOrder->meter_number,
                'installation_address' => $serviceOrder->installation_address,
                'contract' => $serviceOrder->contract ? [
                    'status' => $serviceOrder->contract->status,
                    'signed_url' => $serviceOrder->contract->signed_url,
                    'generated_at' => $serviceOrder->contract->generated_at?->format('Y-m-d'),
                ] : null,
            ],
            'client' => [
                'id' => $client->id,
                'name' => $client->name,
                'tax_id' => $client->tax_id,
                'full_address' => $client->full_address,
                'contact_email' => $mainContact->email ?? null,
                'contact_phone' => $mainContact->phone ?? null,
            ],
            'installments' => $installments,
            'payments' => $payments,
            'abonos' => $abonos,
            'balance' => PortfolioService::balanceForOrder($serviceOrder),
            'methods' => PortalPayment::METHODS,
        ]);
    }
}
