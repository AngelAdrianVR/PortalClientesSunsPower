<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Payment;
use App\Models\PortalPayment;
use App\Services\PortfolioService;
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
            'recentPayments' => $client ? $this->recentPayments($client) : [],
            'recentAbonos' => $client ? $this->recentAbonos($client) : [],
        ]);
    }

    private function recentPayments(Client $client): array
    {
        return $client->payments()
            ->with('media')
            ->latest('payment_date')
            ->limit(5)
            ->get()
            ->map(fn (Payment $p) => [
                'id' => $p->id,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'amount' => round((float) $p->amount, 2),
                'method' => $p->method,
                'service_number' => $p->serviceOrder?->service_number,
                'receipt_id' => $p->getFirstMedia('receipts')?->id,
            ])
            ->values()
            ->all();
    }

    private function recentAbonos(Client $client): array
    {
        return $client->portalPayments()
            ->with('media')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn (PortalPayment $p) => [
                'id' => $p->id,
                'payment_date' => $p->payment_date?->format('Y-m-d'),
                'amount' => round((float) $p->amount, 2),
                'status' => $p->status,
                'service_number' => $p->serviceOrder?->service_number,
            ])
            ->values()
            ->all();
    }
}
