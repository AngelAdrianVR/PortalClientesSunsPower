/**
 * Planes de pago del ERP (columna service_orders.payment_method).
 *
 * Para los planes de mensualidades fijas ("3 MSI", "6 MSI", "9 MSI", "12 MSI")
 * el ERP crea cada cuota con su monto fijo, por lo que en el portal ese monto
 * NO debe poder editarse al registrar un pago. Con plan "Contado" o
 * "Personalizado" el cliente puede abonar cualquier cantidad; sin plan asignado
 * (NULL/vacío) NO se permite registrar pagos (ver hasPaymentPlan).
 */

const FIXED_PLAN_MONTHS = ['3', '6', '9', '12'];

/**
 * Mensaje mostrado cuando el proveedor aún no asigna un plan de pago:
 * sin plan no es posible registrar pagos desde el portal.
 */
export const NO_PLAN_MESSAGE =
    'Aún no tienes un plan de pago asignado, por lo que no es posible registrar pagos. Comunícate con el proveedor para más información.';

/** ¿El servicio tiene un plan de pago asignado por el proveedor? (NULL/vacío = aún no). */
export function hasPaymentPlan(paymentMethod) {
    return String(paymentMethod ?? '').trim() !== '';
}

/** ¿El método/plan de pago corresponde a un plan de mensualidades fijas? */
export function isFixedPaymentPlan(paymentMethod) {
    const s = String(paymentMethod ?? '').trim().toLowerCase();

    if (!s) {
        return false;
    }

    const match = s.match(/(\d+)\s*(msi|meses|mes|month)/);

    return !!match && FIXED_PLAN_MONTHS.includes(match[1]);
}
