<?php

namespace App\Services;

use App\Models\Client;
use App\Models\Payment;
use App\Models\PaymentInstallment;
use App\Models\PortalPayment;
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
        // OJO: el alias NO debe llamarse `principal`: Payment tiene un accessor
        // getPrincipalAttribute() que enmascararía la columna agregada y devolvería 0.
        $row = $client->payments()
            ->selectRaw('COALESCE(SUM(amount), 0) - COALESCE(SUM(interest_amount), 0) as paid_principal')
            ->first();

        return round((float) ($row->paid_principal ?? 0), 2);
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
        // Mismo cuidado que en paidPrincipal(): alias distinto de `principal`.
        $row = $order->payments()
            ->selectRaw('COALESCE(SUM(amount), 0) - COALESCE(SUM(interest_amount), 0) as paid_principal')
            ->first();

        $paid = (float) ($row->paid_principal ?? 0);

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

        $serviceMeta = $orders->mapWithKeys(fn (ServiceOrder $o) => [
            $o->id => [
                'id' => $o->id,
                'service_number' => $o->service_number ?: 'Orden #'.$o->id,
                'payment_method' => $o->payment_method,
            ],
        ])->all();

        $installments = PaymentInstallment::query()
            ->whereIn('service_order_id', array_keys($serviceMeta))
            ->whereNull('payment_id')
            ->whereNotIn('status', ['paid', 'on_time'])
            ->get()
            ->sortBy(fn (PaymentInstallment $i) => $i->projected_date->timestamp)
            ->values();

        // Saldo a capital por servicio (una sola consulta) para el diálogo de pago.
        $balances = ServiceOrder::query()
            ->whereIn('id', array_keys($serviceMeta))
            ->addSelect(['id', 'total_amount', 'paid_principal' => self::paidPrincipalSubquery()])
            ->get()
            ->mapWithKeys(fn (ServiceOrder $o) => [
                $o->id => round(max(0.0, (float) $o->total_amount - (float) ($o->paid_principal ?? 0)), 2),
            ])
            ->all();

        $inReview = $client->portalPayments()
            ->with(['serviceOrder', 'media'])
            ->where('status', PortalPayment::STATUS_IN_REVIEW)
            ->latest()
            ->get();

        // Cuotas con un abono "En revisión" del portal: la fila se muestra como
        // "Pendiente de revisión" en lugar de habilitar otro pago de la misma
        // mensualidad (el abono aún no descuenta saldo hasta ser validado).
        $pendingKeys = [];
        $legacyAmounts = [];

        foreach ($inReview as $p) {
            if ($p->installment_number) {
                $pendingKeys[$p->service_order_id.':'.$p->installment_number] = true;
            } else {
                // Abonos capturados antes de guardar la cuota: se asocian por monto
                // a la primera cuota impaga que coincida (sin marcar las demás).
                $legacyAmounts[$p->service_order_id][] = round((float) $p->amount, 2);
            }
        }

        foreach ($installments as $i) {
            foreach ($legacyAmounts[$i->service_order_id] ?? [] as $index => $amount) {
                if (abs(round((float) $i->amount, 2) - $amount) < 0.01
                    || abs($i->total_with_interest - $amount) < 0.01) {
                    $pendingKeys[$i->service_order_id.':'.$i->installment_number] = true;
                    unset($legacyAmounts[$i->service_order_id][$index]);
                    break;
                }
            }
        }

        // Abonos generales "En revisión" que no corresponden a ninguna cuota
        // (p. ej. el pago del saldo no cubierto en un plan Personalizado).
        $unmatchedByOrder = [];

        foreach ($legacyAmounts as $orderId => $amounts) {
            $unmatchedByOrder[$orderId] = round((float) array_sum($amounts), 2);
        }

        // Abonos rechazados recientes (con motivo): el cliente ve por qué no
        // fueron validados y puede reintentar con un comprobante válido.
        $rejected = $client->portalPayments()
            ->with(['serviceOrder', 'media'])
            ->where('status', PortalPayment::STATUS_REJECTED)
            ->latest('id')
            ->limit(10)
            ->get();

        // Convierte una cuota impaga en la fila del panel "Pagos restantes".
        $mapRemaining = function (PaymentInstallment $i) use ($serviceMeta, $balances, $pendingKeys): array {
            $meta = $serviceMeta[$i->service_order_id] ?? ['service_number' => 'Orden #'.$i->service_order_id];
            $overdue = $i->days_late > 0;

            return [
                'service_id' => $i->service_order_id,
                'service_number' => $meta['service_number'],
                'payment_method' => $meta['payment_method'] ?? null,
                // El proveedor aún no asigna plan de pago: no se permite pagar.
                'plan_missing' => blank($meta['payment_method'] ?? null),
                'service_balance' => $balances[$i->service_order_id] ?? 0.0,
                'installment_number' => $i->installment_number,
                'label' => $i->label,
                'projected_date' => $i->projected_date->format('Y-m-d'),
                'amount' => round((float) $i->amount, 2),
                'interest' => $i->calculateInterest(),
                'total_with_interest' => $i->total_with_interest,
                'days_late' => $i->days_late,
                'overdue' => $overdue,
                // Ya existe un abono del portal esperando validación en el ERP.
                'pending_review' => isset($pendingKeys[$i->service_order_id.':'.$i->installment_number]),
                // Próxima a vencer con una semana de anticipación (incluye las que
                // aún están dentro de los días de gracia sin generar recargo).
                'near_due' => ! $overdue && $i->projected_date->lte(now()->addDays(7)->startOfDay()),
            ];
        };

        $remaining = $installments->map($mapRemaining)->all();

        $overdueList = array_values(array_filter($remaining, fn (array $r) => $r['overdue']));
        $upcomingList = array_values(array_filter($remaining, fn (array $r) => ! $r['overdue']));

        // Suma de cuotas pendientes por orden: indica si la proyección cubre o no
        // el saldo pendiente del servicio.
        $pendingByOrder = $installments
            ->groupBy('service_order_id')
            ->map(fn ($items) => round((float) $items->sum('amount'), 2))
            ->all();

        // Plan Personalizado: las cuotas las define el proveedor manualmente, así
        // que puede quedar saldo sin cuota proyectada. El cliente puede pagarlo.
        $uncoveredPayments = [];

        // Servicios sin plan de pago asignado por el proveedor: el portal
        // deshabilita el registro de pagos y pide contactar al proveedor.
        $servicesWithoutPlan = [];

        foreach ($serviceMeta as $id => $meta) {
            $balance = $balances[$id] ?? 0.0;

            if ($balance <= 0) {
                continue;
            }

            if (blank($meta['payment_method'])) {
                $servicesWithoutPlan[] = [
                    'service_id' => $id,
                    'service_number' => $meta['service_number'],
                    'service_balance' => $balance,
                ];

                continue;
            }

            if ($meta['payment_method'] !== 'Personalizado') {
                continue;
            }

            $projected = $pendingByOrder[$id] ?? 0.0;
            $uncovered = round($balance - $projected, 2);

            // La proyección ya cubre todo el saldo: se paga desde las cuotas.
            if ($uncovered < 0.005) {
                continue;
            }

            $uncoveredPayments[] = [
                'service_id' => $id,
                'service_number' => $meta['service_number'],
                'payment_method' => $meta['payment_method'],
                'service_balance' => $balance,
                'projected_total' => $projected,
                'uncovered_amount' => $uncovered,
                // Ya existe un abono general "En revisión" por este saldo.
                'pending_review' => ($unmatchedByOrder[$id] ?? 0.0) >= $uncovered - 0.005,
            ];
        }

        // Todas las órdenes del cliente (cualquier estado): conteo y monto total.
        $allOrders = $client->serviceOrders()->get(['id', 'total_amount']);

        return [
            'services_count' => $allOrders->count(),
            'services_total' => round((float) $allOrders->sum('total_amount'), 2),
            'total_balance' => self::balanceFor($client),
            'overdue_installments' => count($overdueList),
            'overdue_interest' => round(array_sum(array_column($overdueList, 'interest')), 2),
            'next_due' => $upcomingList[0] ?? null,
            'pending_review_count' => $inReview->count(),
            'pending_review_total' => round((float) $inReview->sum('amount'), 2),
            // Listas para el panel general
            'remaining_payments' => $remaining,
            'upcoming_dues' => $upcomingList,
            'overdue_dues' => $overdueList,
            // Saldo que la proyección no cubre (plan Personalizado): el cliente
            // puede registrar un pago libre desde el panel general.
            'uncovered_payments' => $uncoveredPayments,
            // Servicios sin plan de pago asignado (solo para mostrar el aviso).
            'services_without_plan' => $servicesWithoutPlan,
            'pending_review_abonos' => $inReview->map(function (PortalPayment $p) use ($serviceMeta): array {
                $meta = $serviceMeta[$p->service_order_id] ?? null;

                return [
                    'id' => $p->id,
                    'payment_date' => $p->payment_date?->format('Y-m-d'),
                    'created_at' => $p->created_at?->format('Y-m-d H:i'),
                    'amount' => round((float) $p->amount, 2),
                    'method' => $p->method,
                    'reference' => $p->reference,
                    'service_number' => $meta['service_number'] ?? ($p->serviceOrder?->service_number ?? 'Orden #'.$p->service_order_id),
                    'receipt_url' => ReceiptService::url($p->getFirstMedia('receipts')),
                ];
            })->all(),
            // Rechazados: motivo visible y datos para volver a pagar.
            'rejected_abonos' => $rejected->map(function (PortalPayment $p) use ($serviceMeta, $balances): array {
                $meta = $serviceMeta[$p->service_order_id] ?? null;

                return [
                    'id' => $p->id,
                    'service_id' => $p->service_order_id,
                    'service_number' => $meta['service_number'] ?? ($p->serviceOrder?->service_number ?? 'Orden #'.$p->service_order_id),
                    'installment_number' => $p->installment_number,
                    'service_balance' => $balances[$p->service_order_id] ?? 0.0,
                    'payment_method' => $meta['payment_method'] ?? null,
                    // El proveedor aún no asigna plan de pago: no se puede reintentar.
                    'plan_missing' => blank($meta['payment_method'] ?? null),
                    'payment_date' => $p->payment_date?->format('Y-m-d'),
                    'amount' => round((float) $p->amount, 2),
                    'method' => $p->method,
                    'rejection_reason' => $p->rejection_reason,
                    'validated_at' => $p->validated_at?->format('Y-m-d H:i'),
                    'receipt_url' => ReceiptService::url($p->getFirstMedia('receipts')),
                ];
            })->all(),
        ];
    }
}
