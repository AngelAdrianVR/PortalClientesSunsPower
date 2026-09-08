<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\ServiceOrder;
use App\Services\PortfolioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StatementController extends Controller
{
    /**
     * Vista en pantalla del estado de cuenta (pestaña nueva, sin AppLayout).
     * Permite ver un servicio concreto o todos los servicios del cliente.
     */
    public function view(Request $request): Response
    {
        /** @var Client $client */
        $client = $request->user('portal');
        $client->load('contacts');

        return Inertia::render('EstadoCuenta', [
            'client' => $this->clientPayload($client),
            'services' => $this->serviceStatements($client),
            'generatedAt' => now()->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Descarga el estado de cuenta de un servicio concreto en PDF.
     */
    public function download(Request $request, ServiceOrder $serviceOrder)
    {
        abort_unless($serviceOrder->client_id === (int) $request->user('portal')?->id, 404);

        $serviceOrder->load(['client.contacts', 'payments', 'paymentInstallments']);

        $payload = $this->serviceStatement($serviceOrder);

        $pdf = Pdf::loadView('pdf.estado-cuenta', [
            'payload' => $payload,
            'client' => $serviceOrder->client,
            'generatedAt' => now(),
            // dompdf necesita GD para embeber PNG; sin GD se omite el logotipo.
            'logo' => extension_loaded('gd') ? public_path('images/isologo-suns-power-mx.png') : null,
        ])->setPaper('letter');

        $name = 'estado-de-cuenta-'.($serviceOrder->service_number ?: $serviceOrder->id).'.pdf';

        return $pdf->download($name);
    }

    /**
     * Descarga un PDF con el estado de cuenta de TODOS los servicios del cliente.
     */
    public function downloadAll(Request $request)
    {
        /** @var Client $client */
        $client = $request->user('portal');

        $services = $this->serviceStatements($client);

        abort_if(empty($services), 404, 'No hay servicios para generar el estado de cuenta.');

        $pdf = Pdf::loadView('pdf.estado-cuenta-general', [
            'client' => $client,
            'services' => $services,
            'generatedAt' => now(),
            'logo' => extension_loaded('gd') ? public_path('images/isologo-suns-power-mx.png') : null,
        ])->setPaper('letter');

        $name = 'estado-de-cuenta-general.pdf';

        return $pdf->download($name);
    }

    /** Datos básicos del cliente para la vista en pantalla. */
    private function clientPayload(Client $client): array
    {
        $mainContact = $client->contacts->firstWhere('is_primary', true) ?? $client->contacts->first();

        return [
            'id' => $client->id,
            'name' => $client->name,
            'tax_id' => $client->tax_id,
            'full_address' => $client->full_address,
            'email' => $mainContact?->email,
            'phone' => $mainContact?->phone,
        ];
    }

    /** Estado de cuenta (datos) de un solo servicio. */
    private function serviceStatement(ServiceOrder $o): array
    {
        $balance = PortfolioService::balanceForOrder($o);

        return [
            'id' => $o->id,
            'service_number' => $o->service_number ?: 'Orden #'.$o->id,
            'system_type' => $o->system_type,
            'status' => $o->status,
            'start_date' => $o->start_date?->format('Y-m-d'),
            'payment_method' => $o->payment_method,
            'installation_address' => $o->installation_address,
            'down_payment' => round((float) $o->down_payment, 2),
            'total_amount' => round((float) $o->total_amount, 2),
            'balance' => $balance,
            'paid' => round(max(0.0, (float) $o->total_amount - $balance), 2),
            'overdue_interest' => round(
                $o->paymentInstallments->sum(fn ($i) => $i->calculateInterest()),
                2
            ),
            'installments' => $o->paymentInstallments
                ->sortBy('installment_number')
                ->values()
                ->map(fn ($i) => [
                    'installment_number' => $i->installment_number,
                    'label' => $i->label,
                    'projected_date' => $i->projected_date?->format('Y-m-d'),
                    'amount' => round((float) $i->amount, 2),
                    'status' => $i->current_status,
                    'interest' => $i->calculateInterest(),
                    'total_with_interest' => $i->total_with_interest,
                ])
                ->all(),
            'payments' => $o->payments
                ->sortByDesc(fn ($p) => $p->payment_date?->timestamp ?? 0)
                ->values()
                ->map(fn ($p) => [
                    'payment_date' => $p->payment_date?->format('Y-m-d'),
                    'amount' => round((float) $p->amount, 2),
                    'interest_amount' => round((float) $p->interest_amount, 2),
                    'method' => $p->method,
                    'reference' => $p->reference,
                ])
                ->all(),
        ];
    }

    /** Estado de cuenta (datos) por cada servicio visible del cliente. */
    private function serviceStatements(Client $client): array
    {
        return $client->serviceOrders()
            ->whereIn('status', ServiceOrder::PORTAL_STATUSES)
            ->with(['client.contacts', 'payments', 'paymentInstallments'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ServiceOrder $o) => $this->serviceStatement($o))
            ->values()
            ->all();
    }
}
