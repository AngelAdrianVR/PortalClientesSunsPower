<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { fmtMoney, fmtDate } from '@/utils/format';

/**
 * Estado de cuenta en pantalla (se abre en pestaña nueva, SIN AppLayout).
 * Permite elegir un servicio concreto o todos; el botón descarga el PDF y
 * después pregunta si se desea cerrar la pestaña.
 */
const props = defineProps({
    client: { type: Object, required: true },
    services: { type: Array, default: () => [] },
    generatedAt: { type: String, default: '' },
});

const scope = ref('all');
const confirmVisible = ref(false);

function initialScope() {
    const q = new URLSearchParams(window.location.search).get('servicio');

    if (q && props.services.some((s) => String(s.id) === q)) {
        return q;
    }

    return 'all';
}

scope.value = initialScope();

const scopeOptions = computed(() => [
    { value: 'all', label: `Todos mis servicios (${props.services.length})` },
    ...props.services.map((s) => ({ value: String(s.id), label: s.service_number })),
]);

const selectedServices = computed(() => {
    if (scope.value === 'all') {
        return props.services;
    }

    return props.services.filter((s) => String(s.id) === scope.value);
});

const statusMeta = {
    paid: { label: 'Pagada', cls: 'is-ok' },
    on_time: { label: 'Pagada a tiempo', cls: 'is-ok' },
    upcoming: { label: 'Por vencer', cls: 'is-info' },
    pending: { label: 'Pendiente', cls: 'is-warn' },
    late: { label: 'Vencida', cls: 'is-warn' },
    defaulted: { label: 'Vencida (+10 días)', cls: 'is-bad' },
};

function statusOf(status) {
    return statusMeta[status] || { label: status, cls: 'is-info' };
}

function downloadPdf() {
    if (!selectedServices.value.length) {
        return;
    }

    const url = scope.value === 'all' ? route('statement.download-all') : route('services.statement', Number(scope.value));

    const a = document.createElement('a');
    a.href = url;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    a.remove();

    // Después de iniciar la descarga se pregunta si se desea cerrar la pestaña.
    confirmVisible.value = true;
}

function closeTab() {
    window.close();
}
</script>

<template>
    <Head title="Estado de Cuenta" />

    <div class="st-page">
        <!-- Barra de acciones (solo en pantalla) -->
        <div class="st-toolbar">
            <div class="st-brand">
                <img src="/images/isologo-suns-power-mx.png" alt="SUN'S POWER MX" class="st-logo" onerror="this.style.display='none'" />
                <div>
                    <div class="st-brand-name">SUN'S POWER MX</div>
                    <div class="st-brand-sub">Estado de cuenta</div>
                </div>
            </div>

            <div class="st-actions">
                <span v-if="generatedAt" class="st-gen">Generado: {{ generatedAt }}</span>
                <el-select v-if="services.length > 1" v-model="scope" class="st-select" placeholder="Servicio">
                    <el-option v-for="opt in scopeOptions" :key="opt.value" :label="opt.label" :value="opt.value" />
                </el-select>
                <el-button type="primary" class="st-download" :disabled="!selectedServices.length" @click="downloadPdf">
                    <el-icon><Download /></el-icon>
                    Descargar estado de cuenta
                </el-button>
            </div>
        </div>

        <div class="st-sheet">
            <!-- Encabezado del documento -->
            <div class="st-doc-head">
                <div class="st-doc-brand">
                    <img src="/images/isologo-suns-power-mx.png" alt="" class="st-doc-logo" onerror="this.style.display='none'" />
                    <div>
                        <div class="st-doc-name">SUN'S POWER MX</div>
                        <div class="st-doc-sub">Energía solar · Portal de Clientes</div>
                    </div>
                </div>
                <div class="st-doc-title">
                    <h1>Estado de Cuenta</h1>
                    <div v-if="generatedAt">Generado: {{ generatedAt }}</div>
                    <div>{{ scope === 'all' ? 'Todos los servicios' : '' }}</div>
                </div>
            </div>

            <div class="st-client">
                <h3>Cliente</h3>
                <div class="st-table-scroll">
                    <table class="st-table">
                        <tbody>
                            <tr>
                                <th>Nombre</th>
                                <th>RFC</th>
                                <th>Dirección</th>
                            </tr>
                            <tr>
                                <td>{{ client.name }}</td>
                                <td>{{ client.tax_id || '—' }}</td>
                                <td>{{ client.full_address || '—' }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <el-empty
                v-if="!services.length"
                description="No tienes servicios para mostrar el estado de cuenta."
                :image-size="80"
            />

            <template v-else>
                <section v-for="svc in selectedServices" :key="svc.id" class="st-service">
                    <div class="st-service-title">
                        <span>Servicio: {{ svc.service_number }}</span>
                        <el-tag size="small" effect="light">{{ svc.status }}</el-tag>
                    </div>

                    <div class="st-block">
                        <h3>Servicio</h3>
                        <div class="st-table-scroll">
                            <table class="st-table">
                                <tbody>
                                    <tr>
                                        <th>Tipo de sistema</th>
                                        <th>Estatus</th>
                                        <th>Inicio</th>
                                        <th>Dirección</th>
                                        <th>Plan de pago</th>
                                    </tr>
                                    <tr>
                                        <td>{{ svc.system_type || '—' }}</td>
                                        <td>{{ svc.status }}</td>
                                        <td>{{ fmtDate(svc.start_date) }}</td>
                                        <td>{{ svc.installation_address || '—' }}</td>
                                        <td>{{ svc.payment_method || '—' }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="st-block">
                        <h3>Cuotas del plan de pago</h3>
                        <div class="st-table-scroll">
                            <table class="st-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Concepto</th>
                                        <th>Vencimiento</th>
                                        <th class="is-right">Monto</th>
                                        <th>Estatus</th>
                                        <th class="is-right">Interés</th>
                                        <th class="is-right">Total a pagar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="svc.installments.length">
                                        <tr v-for="i in svc.installments" :key="i.installment_number">
                                            <td>{{ i.installment_number }}</td>
                                            <td>{{ i.label }}</td>
                                            <td>{{ fmtDate(i.projected_date) }}</td>
                                            <td class="is-right">{{ fmtMoney(i.amount) }}</td>
                                            <td :class="statusOf(i.status).cls">{{ statusOf(i.status).label }}</td>
                                            <td class="is-right" :class="{ 'is-interest': i.interest > 0 }">{{ fmtMoney(i.interest) }}</td>
                                            <td class="is-right">{{ fmtMoney(i.total_with_interest) }}</td>
                                        </tr>
                                    </template>
                                    <tr v-else>
                                        <td colspan="7">Este servicio no tiene cuotas registradas (plan personalizado).</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="st-block">
                        <h3>Pagos realizados</h3>
                        <div class="st-table-scroll">
                            <table class="st-table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th class="is-right">Monto</th>
                                        <th class="is-right">Interés</th>
                                        <th>Método</th>
                                        <th>Referencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <template v-if="svc.payments.length">
                                        <tr v-for="(p, idx) in svc.payments" :key="idx">
                                            <td>{{ fmtDate(p.payment_date) }}</td>
                                            <td class="is-right">{{ fmtMoney(p.amount) }}</td>
                                            <td class="is-right">{{ fmtMoney(p.interest_amount) }}</td>
                                            <td>{{ p.method || '—' }}</td>
                                            <td>{{ p.reference || '—' }}</td>
                                        </tr>
                                    </template>
                                    <tr v-else>
                                        <td colspan="5">Aún no hay pagos registrados.</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <table class="st-totals">
                        <tbody>
                            <tr>
                                <td>Costo total del servicio:</td>
                                <td class="is-right">{{ fmtMoney(svc.total_amount) }}</td>
                            </tr>
                            <tr>
                                <td>Pagado a capital:</td>
                                <td class="is-right">{{ fmtMoney(svc.paid) }}</td>
                            </tr>
                            <tr v-if="svc.overdue_interest > 0" class="st-interest">
                                <td>Interés moratorio acumulado:</td>
                                <td class="is-right">{{ fmtMoney(svc.overdue_interest) }}</td>
                            </tr>
                            <tr class="st-grand">
                                <td>Saldo pendiente:</td>
                                <td class="is-right">{{ fmtMoney(svc.balance) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </section>
            </template>

            <div class="st-foot">
                Interés moratorio: 10% mensual sobre saldos vencidos con 5 días de gracia. Documento informativo generado desde el portal de clientes.
            </div>
        </div>

        <!-- Modal tras la descarga -->
        <el-dialog v-model="confirmVisible" title="Descarga iniciada" width="min(420px, 95vw)" :close-on-click-modal="false">
            <p class="st-modal-text">¿Deseas cerrar la pestaña?</p>
            <p class="st-modal-sub">El estado de cuenta se está descargando. Puedes cerrar esta pestaña o mantenerla abierta.</p>
            <template #footer>
                <el-button @click="confirmVisible = false">No</el-button>
                <el-button type="primary" @click="closeTab">Sí</el-button>
            </template>
        </el-dialog>
    </div>
</template>

<style>
/* Estilos globales de esta página (sin AppLayout). */
.st-page {
    min-height: 100vh;
    background: #eef2ff;
    padding: 18px 16px 40px;
    font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
}

.st-toolbar {
    max-width: 1060px;
    margin: 0 auto 16px;
    background: #1e3a8a;
    color: #fff;
    border-radius: 14px;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.st-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.st-logo {
    width: 42px;
    height: 42px;
    object-fit: contain;
    border-radius: 10px;
    background: #fff;
    padding: 3px;
}

.st-brand-name {
    font-weight: 800;
    letter-spacing: 0.08em;
    font-size: 14px;
}

.st-brand-sub {
    font-size: 12px;
    opacity: 0.85;
}

.st-actions {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.st-gen {
    font-size: 12px;
    opacity: 0.9;
}

.st-toolbar .st-download {
    background: #facc15;
    border-color: #facc15;
    color: #1e3a8a;
    font-weight: 700;
}

.st-toolbar .st-download:hover {
    background: #eab308;
    border-color: #eab308;
    color: #1e3a8a;
}

.st-select {
    width: 230px;
}

.st-sheet {
    max-width: 1060px;
    margin: 0 auto;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 10px 30px -18px rgba(30, 58, 138, 0.5);
    padding: 28px 30px;
}

.st-doc-head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 3px solid #1e3a8a;
    padding-bottom: 12px;
    margin-bottom: 16px;
    gap: 12px;
    flex-wrap: wrap;
}

.st-doc-brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.st-doc-logo {
    width: 60px;
    height: 60px;
    object-fit: contain;
    border-radius: 14px;
    border: 1px solid #e6e9ef;
    padding: 4px;
    background: #fff;
}

.st-doc-name {
    font-size: 18px;
    font-weight: 800;
    color: #1e3a8a;
}

.st-doc-sub {
    font-size: 11px;
    color: #64748b;
}

.st-doc-title {
    text-align: right;
}

.st-doc-title h1 {
    margin: 0 0 2px;
    font-size: 20px;
    color: #1e3a8a;
}

.st-doc-title div {
    font-size: 12px;
    color: #64748b;
}

.st-client,
.st-block {
    border: 1px solid #dbe3f5;
    border-radius: 10px;
    padding: 12px 14px;
    margin-bottom: 14px;
}

.st-client h3,
.st-block h3 {
    margin: 0 0 8px;
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 0.4px;
    color: #1e3a8a;
    border-left: 4px solid #facc15;
    padding-left: 8px;
}

.st-table {
    width: 100%;
    border-collapse: collapse;
}

.st-table th,
.st-table td {
    border: 1px solid #e5e7eb;
    padding: 6px 8px;
    text-align: left;
    vertical-align: top;
    font-size: 13px;
}

.st-table th {
    background: #eef2ff;
    color: #1e3a8a;
    font-size: 11px;
    text-transform: uppercase;
}

/* Contenedor de scroll horizontal por tabla (evita desbordes en móvil). */
.st-table-scroll {
    overflow-x: auto;
    overflow-y: hidden;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    max-width: 100%;
}

.st-table-scroll .st-table {
    min-width: 560px;
    width: 100%;
}

.st-block .st-table-scroll .st-table {
    min-width: 700px;
}

.st-table .is-right {
    text-align: right;
}

.is-ok {
    color: #15803d;
    font-weight: 600;
}

.is-warn {
    color: #b45309;
    font-weight: 600;
}

.is-bad {
    color: #b91c1c;
    font-weight: 600;
}

.is-info {
    color: #1e3a8a;
}

.is-interest {
    color: #b91c1c;
    font-weight: 600;
}

.st-service {
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 18px;
    background: #fbfdff;
}

.st-service-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 15px;
    font-weight: 700;
    color: #1e3a8a;
    border-bottom: 2px solid #facc15;
    padding-bottom: 8px;
    margin-bottom: 12px;
}

.st-totals {
    width: 55%;
    margin-left: auto;
    border-collapse: collapse;
}

.st-totals td {
    padding: 5px 10px;
    border: none;
    color: #475569;
    font-size: 13px;
}

.st-totals .is-right {
    text-align: right;
}

.st-totals .st-grand td {
    border-top: 2px solid #1e3a8a;
    font-weight: 700;
    color: #1e3a8a;
}

.st-totals .st-interest td {
    color: #b91c1c;
}

.st-foot {
    margin-top: 16px;
    border-top: 1px solid #e5e7eb;
    padding-top: 8px;
    text-align: center;
    font-size: 11px;
    color: #94a3b8;
}

.st-modal-text {
    margin: 0 0 6px;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.st-modal-sub {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

@media print {
    .st-toolbar,
    .el-dialog__wrapper,
    .el-overlay {
        display: none !important;
    }

    .st-page {
        background: #fff;
        padding: 0;
    }

    .st-sheet {
        box-shadow: none;
        border-radius: 0;
        padding: 0;
    }

    .st-service {
        page-break-inside: avoid;
    }
}
</style>
