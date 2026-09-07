<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentInstallment;
use App\Models\ServiceOrder;

/**
 * Cálculos de cartera del cliente replicando EXACTAMENTE las reglas del ERP:
 *  - Saldo = Σ total_amount (órdenes no Cotización/Cancelado) − Σ pagos que abonan capital.
 *  - Los pagos con `interest_amount` solo descuentan la parte de capital (amount − interest).
 *  - Interés moratorio: 10% mensual compuesto, 5 días de gracia.
 */
class PortfolioService
{
    public const VISIBLE_STATUSES = ['Aceptado', 'En Proceso', 'Completado', 'Facturado'];

    /** Capital pagado por el cliente (excluye intereses moratorios). */
    public static function paidPrincipal(Client $client): float
    {
        $row = $client->payments()
            ->selectRaw('COALESCE(SUM(amount), 0) - COALESCE(SUM(interest_amount), 0) as principal')
            ->first();

        return round((float) ($row->principal ?? 0), 2);
    }

    /** Saldo pendiente global del cliente (misma fórmula del ERP). */
    public static function balanceFor(Client $client): float
    {
        $total = (float) $client->serviceOrders()
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->sum('total_amount');

        return round(max(0.0, $total - self::paidPrincipal($client)), 2);
    }

    /** Saldo de una orden: total − pagos atribuidos a esa orden. */
    public static function balanceForOrder(ServiceOrder $order): float
    {
        $row = $order->payments()
            ->selectRaw('COALESCE(SUM(amount), 0) - COALESCE(SUM(interest_amount), 0) as principal')
            ->first();

        $paid = (float) ($row->principal ?? 0);

        return round(max(0.0, (float) $order->total_amount - $paid), 2);
    }

    /** Subconsulta para cargar el capital pagado por orden en una sola query. */
    public static function paidPrincipalSubquery()
    {
        return Payment::query()
            ->selectRaw('COALESCE(SUM(amount), 0) - COALESCE(SUM(interest_amount), 0)')
            ->whereColumn('payments.service_order_id', 'service_orders.id');
    }

    /** Resumen para el dashboard del portal. */
    public static function summary(Client $client): array
    {
        $orders = $client->serviceOrders()
            ->whereIn('status', self::VISIBLE_STATUSES)
            ->get();

        $installments = PaymentInstallment::query()
            ->whereIn('service_order_id', $orders->pluck('id'))
            ->whereNull('payment_id')
            ->whereNotIn('status', ['paid', 'on_time'])
            ->get();

        $overdue = $installments->filter(fn (PaymentInstallment $i) => $i->days_late > 0);

        $next = $installments
            ->filter(fn (PaymentInstallment $i) => $i->projected_date->gte(today()))
            ->sortBy(fn (PaymentInstallment $i) => $i->projected_date->timestamp)
            ->first();

        $inReview = $client->portalPayments()
            ->where('status', 'En revisión')
            ->get();

        return [
            'services_count' => $orders->count(),
            'total_balance' => self::balanceFor($client),
            'overdue_installments' => $overdue->count(),
            'overdue_interest' => round($overdue->sum(fn (PaymentInstallment $i) => $i->calculateInterest()), 2),
            'next_due' => $next ? [
                'projected_date' => $next->projected_date->format('Y-m-d'),
                'label' => $next->label,
                'amount' => round((float) $next->amount, 2),
            ] : null,
            'pending_review_count' => $inReview->count(),
            'pending_review_total' => round((float) $inReview->sum('amount'), 2),
        ];
    }
}
