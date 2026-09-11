<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PortalPayment;
use App\Services\PortfolioService;
use App\Services\ReceiptService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        /** @var \App\Models\Client|null $client */
        $client = $request->user('portal');

        return Inertia::render('Dashboard', [
            'summary' => $client ? PortfolioService::summary($client) : null,
            'payments' => $client ? $this->paymentHistory($client) : [],
            'methods' => PortalPayment::METHODS,
        ]);
    }

    /** Historial completo de pagos validados del cliente. */
    private function paymentHistory(Client $client): array
    {
        return $client->payments()
            ->with(['serviceOrder', 'media'])
            ->latest('payment_date')
            ->latest('id')
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'amount' => round((float) $p->amount, 2),
                'method' => $p->method,
                'reference' => $p->reference,
                'notes' => $p->notes,
                'service_number' => $p->serviceOrder?->service_number,
                'receipt_url' => ReceiptService::url($p->getFirstMedia('receipts')),
            ])
            ->values()
            ->all();
    }
}
