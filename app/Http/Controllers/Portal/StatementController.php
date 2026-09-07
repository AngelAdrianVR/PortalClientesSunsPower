<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\ServiceOrder;
use App\Services\PortfolioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class StatementController extends Controller
{
    /**
     * Descarga el estado de cuenta del servicio en PDF.
     */
    public function download(Request $request, ServiceOrder $serviceOrder)
    {
        abort_unless($serviceOrder->client_id === (int) $request->user('portal')?->id, 404);

        $serviceOrder->load(['client.contacts', 'payments', 'paymentInstallments']);

        $installments = $serviceOrder->paymentInstallments
            ->sortBy(fn ($i) => $i->installment_number)
            ->values();

        $payments = $serviceOrder->payments
            ->sortByDesc(fn ($p) => $p->payment_date?->timestamp ?? 0)
            ->values();

        $pdf = Pdf::loadView('pdf.estado-cuenta', [
            'serviceOrder' => $serviceOrder,
            'client' => $serviceOrder->client,
            'installments' => $installments,
            'payments' => $payments,
            'balance' => PortfolioService::balanceForOrder($serviceOrder),
            'generatedAt' => now(),
        ])->setPaper('letter');

        $name = 'estado-de-cuenta-'.($serviceOrder->service_number ?: $serviceOrder->id).'.pdf';

        return $pdf->download($name);
    }
}
