/**
 * Planes de pago del ERP (columna service_orders.payment_method).
 *
 * Para los planes de mensualidades fijas ("3 MSI", "6 MSI", "9 MSI", "12 MSI")
 * el ERP crea cada cuota con su monto fijo, por lo que en el portal ese monto
 * NO debe poder editarse al registrar un pago. Sin plan (NULL/"Contado") o con
 * plan "Personalizado" el cliente puede abonar cualquier cantidad.
 */

const FIXED_PLAN_MONTHS = ['3', '6', '9', '12'];

/** ¿El método/plan de pago corresponde a un plan de mensualidades fijas? */
export function isFixedPaymentPlan(paymentMethod) {
    const s = String(paymentMethod ?? '').trim().toLowerCase();

    if (!s) {
        return false;
    }

    const match = s.match(/(\d+)\s*(msi|meses|mes|month)/);

    return !!match && FIXED_PLAN_MONTHS.includes(match[1]);
}
