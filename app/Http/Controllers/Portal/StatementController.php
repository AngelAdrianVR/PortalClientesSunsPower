<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PaymentInstallment;
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

        // `paymentInstallments.payment` permite desglosar lo pagado por cuota.
        $serviceOrder->load(['client.contacts', 'payments', 'paymentInstallments.payment']);

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

        // Interés moratorio realmente cobrado en cada cuota (puede ser 0).
        $paidBreakdown = $this->paidBreakdownByInstallment($o);

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
                ->map(function ($i) use ($paidBreakdown) {
                    // Interés cobrado y total pagado de ESTA cuota (0 si no se ha pagado).
                    $paidInterest = round((float) ($paidBreakdown[$i->id]['interest_paid'] ?? 0), 2);
                    $paidTotal = round((float) ($paidBreakdown[$i->id]['total_paid'] ?? 0), 2);

                    return [
                        'installment_number' => $i->installment_number,
                        'label' => $i->label,
                        'projected_date' => $i->projected_date?->format('Y-m-d'),
                        'amount' => round((float) $i->amount, 2),
                        'status' => $this->installmentStatus($i),
                        'interest' => $i->calculateInterest(),
                        'total_with_interest' => $i->total_with_interest,
                        // Lo que realmente se pagó en esta cuota.
                        'paid_interest' => $paidInterest,
                        'paid_total' => $paidTotal,
                    ];
                })
                ->all(),
            // Del más antiguo al más reciente (mismo orden en pantalla y PDF).
            'payments' => $o->payments
                ->sortBy([['payment_date', 'asc'], ['id', 'asc']])
                ->values()
                ->map(fn ($p) => [
                    'payment_date' => $p->payment_date?->format('Y-m-d'),
                    // `amount` es el total recibido (capital + interés).
                    'amount' => round((float) $p->amount, 2),
                    'interest_amount' => round((float) $p->interest_amount, 2),
                    'principal' => round((float) $p->amount - (float) $p->interest_amount, 2),
                    'method' => $p->method,
                    'reference' => $p->reference,
                ])
                ->all(),
        ];
    }

    /**
     * Desglose de lo realmente pagado en cada cuota: capital, interés y total.
     *
     * Un mismo pago puede cubrir varias cuotas (p. ej. al liquidar la orden), por
     * eso el capital se asigna en orden de cuota y el interés del pago se reparte
     * proporcionalmente al monto de cada cuota (la última absorbe el redondeo).
     *
     * @return array<int, array{interest_paid: float, total_paid: float}>
     */
    private function paidBreakdownByInstallment(ServiceOrder $order): array
    {
        $breakdown = [];

        foreach ($order->paymentInstallments->groupBy('payment_id') as $paymentId => $group) {
            if (! $paymentId) {
                continue;
            }

            $payment = $group->first()->payment;

            if (! $payment) {
                continue;
            }

            $installments = $group->sortBy('installment_number')->values();
            $capitalRemaining = round(max(0.0, (float) $payment->amount - (float) $payment->interest_amount), 2);
            $interestRemaining = round((float) $payment->interest_amount, 2);
            $totalCapital = (float) $installments->sum(fn ($i) => (float) $i->amount);

            foreach ($installments as $index => $installment) {
                $isLast = $index === $installments->count() - 1;

                $capitalPaid = round(min((float) $installment->amount, $capitalRemaining), 2);
                $capitalRemaining = round($capitalRemaining - $capitalPaid, 2);

                $interestPaid = $isLast
                    ? $interestRemaining
                    : ($totalCapital > 0
                        ? round((float) $payment->interest_amount * ((float) $installment->amount / $totalCapital), 2)
                        : 0.0);
                $interestRemaining = round($interestRemaining - $interestPaid, 2);

                $breakdown[$installment->id] = [
                    'interest_paid' => $interestPaid,
                    'total_paid' => round($capitalPaid + $interestPaid, 2),
                ];
            }
        }

        return $breakdown;
    }

    /**
     * Estatus de una cuota para el estado de cuenta:
     *  - paid: ya pagada (verde)
     *  - due_soon: vence en 7 días o menos (naranja)
     *  - overdue: ya venció (rojo)
     *  - pending: aún no vence y falta más de una semana (texto normal)
     */
    private function installmentStatus(PaymentInstallment $installment): string
    {
        if ($installment->isPaid()) {
            return 'paid';
        }

        $dueDate = $installment->projected_date;

        if (! $dueDate) {
            return 'pending';
        }

        if ($dueDate->lt(today())) {
            return 'overdue';
        }

        return $dueDate->lte(today()->addDays(7)) ? 'due_soon' : 'pending';
    }

    /** Estado de cuenta (datos) por cada servicio visible del cliente. */
    private function serviceStatements(Client $client): array
    {
        return $client->serviceOrders()
            ->whereIn('status', ServiceOrder::PORTAL_STATUSES)
            ->with(['client.contacts', 'payments', 'paymentInstallments.payment'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ServiceOrder $o) => $this->serviceStatement($o))
            ->values()
            ->all();
    }
}
