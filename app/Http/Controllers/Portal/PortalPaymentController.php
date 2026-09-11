<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\PortalPayment;
use App\Models\ServiceOrder;
use App\Services\PortfolioService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PortalPaymentController extends Controller
{
    /**
     * Registra un abono del cliente con comprobante obligatorio.
     * Queda "En revisión" hasta que el personal del ERP lo valide.
     */
    public function store(Request $request)
    {
        /** @var Client $client */
        $client = $request->user('portal');

        $validated = $request->validate([
            'service_order_id' => ['required', 'integer'],
            // Cuota de la proyección que el cliente eligió pagar (si viene de una fila de "Pagos restantes").
            'installment_number' => ['nullable', 'integer', 'min:1'],
            'amount' => ['required', 'numeric', 'min:1', 'max:9999999'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'method' => ['required', 'in:'.implode(',', PortalPayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:10240'],
        ]);

        $serviceOrder = ServiceOrder::where('client_id', $client->id)
            ->findOrFail($validated['service_order_id']);

        // El abono no puede superar el saldo pendiente del servicio.
        $balance = PortfolioService::balanceForOrder($serviceOrder);

        if ((float) $validated['amount'] > $balance + 0.005) {
            throw ValidationException::withMessages([
                'amount' => 'El monto no puede superar el saldo pendiente del servicio ('.number_format($balance, 2).').',
            ]);
        }

        $portalPayment = $client->portalPayments()->create([
            'branch_id' => $client->branch_id,
            'service_order_id' => $serviceOrder->id,
            'installment_number' => $validated['installment_number'] ?? null,
            'amount' => $validated['amount'],
            'payment_date' => $validated['payment_date'],
            'method' => $validated['method'],
            'reference' => $validated['reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'status' => PortalPayment::STATUS_IN_REVIEW,
        ]);

        $portalPayment->addMediaFromRequest('proof')
            ->usingFileName(uniqid('abono-', true).'.'.$request->file('proof')->getClientOriginalExtension())
            ->toMediaCollection('receipts', PortalPayment::RECEIPT_DISK);

        return back()->with('success', 'Tu abono quedó registrado. Estará en revisión hasta que la empresa valide el comprobante y confirme el pago.');
    }
}
