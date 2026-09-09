<script setup>
import { computed, ref } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AbonoDialog from '@/Components/AbonoDialog.vue';
import { fmtMoney, fmtDate } from '@/utils/format';

const props = defineProps({
    summary: { type: Object, default: null },
    payments: { type: Array, default: () => [] },
    methods: { type: Array, default: () => [] },
});

const page = usePage();
const portalClient = computed(() => page.props.portalClient);
const linked = computed(() => Boolean(portalClient.value));

// Modales del panel general
const reviewVisible = ref(false);
const overdueVisible = ref(false);

// Diálogo de pago (registro de abono) desde el propio dashboard
const payVisible = ref(false);
const payServiceId = ref(null);
const payServiceNumber = ref('');
const payBalance = ref(0);
const payInitialAmount = ref(null);
const payPaymentMethod = ref(null);

function goToServices() {
    router.visit(route('services.index'));
}

function openReview() {
    reviewVisible.value = true;
}

function openOverdue() {
    overdueVisible.value = true;
}

/** Abre el estado de cuenta en una pestaña nueva (sin AppLayout). */
function openStatement() {
    window.open(route('statement.view'), '_blank');
}

/**
 * Abre el registro de pago sin salir del dashboard, con el monto de la cuota
 * pre-cargado (total con interés si está vencida, monto base si no) y editable.
 */
function startPayment(row) {
    payServiceId.value = row.service_id;
    payServiceNumber.value = row.service_number || '';
    payBalance.value = Number(row.service_balance || 0);
    payInitialAmount.value = row.overdue ? Number(row.total_with_interest) : Number(row.amount);
    payPaymentMethod.value = row.payment_method || null;
    payVisible.value = true;
}

function onPaySuccess() {
    payVisible.value = false;
    router.reload({ only: ['summary'], preserveScroll: true });
}

/** Clase por fila para pintar vencidas (rojo) y próximas a vencer (naranja). */
function payRowClass({ row }) {
    if (row.overdue) {
        return 'pay-row-overdue';
    }

    if (row.near_due) {
        return 'pay-row-near';
    }

    return '';
}

function dueClass(row) {
    if (row.overdue) {
        return 'due-overdue';
    }

    if (row.near_due) {
        return 'due-near';
    }

    return '';
}
</script>

<template>
    <Head title="Panel general" />

    <AppLayout title="Panel general">
        <template v-if="linked">
            <div class="welcome-banner">
                <div>
                    <h2>Hola, {{ portalClient.name }}</h2>
                    <p>Este es el resumen de tus servicios y pagos.</p>
                </div>
                <el-button type="primary" @click="router.visit(route('services.index'))">
                    Ver mis servicios
                </el-button>
            </div>

            <el-row :gutter="16" class="stat-row">
                <el-col :xs="24" :sm="12" :lg="6">
                    <div class="stat-card">
                        <el-icon :size="30" color="#1e3a8a"><Wallet /></el-icon>
                        <div class="stat-value">{{ fmtMoney(summary.total_balance) }}</div>
                        <div class="stat-label">Saldo pendiente</div>
                        <div v-if="summary.overdue_interest > 0" class="stat-extra">
                            Interés acumulado sin pagar: {{ fmtMoney(summary.overdue_interest) }}
                        </div>
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :lg="6">
                    <div class="stat-card is-clickable" role="button" tabindex="0" @click="openReview()" @keyup.enter="openReview()">
                        <el-icon :size="30" color="#eab308"><Clock /></el-icon>
                        <div class="stat-value">{{ summary.pending_review_count }}</div>
                        <div class="stat-label">Abonos en revisión · {{ fmtMoney(summary.pending_review_total) }}</div>
                        <el-icon class="stat-go"><ArrowRight /></el-icon>
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :lg="6">
                    <div class="stat-card is-clickable" role="button" tabindex="0" @click="goToServices" @keyup.enter="goToServices">
                        <el-icon :size="30" color="#1e3a8a"><Grid /></el-icon>
                        <div class="stat-value">{{ fmtMoney(summary.services_total) }}</div>
                        <div class="stat-label">Total servicios contratados ({{ summary.services_count }})</div>
                        <el-icon class="stat-go"><ArrowRight /></el-icon>
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :lg="6">
                    <div class="stat-card is-clickable" role="button" tabindex="0" @click="openOverdue()" @keyup.enter="openOverdue()">
                        <el-icon :size="30" color="#ef4444"><Warning /></el-icon>
                        <div class="stat-value">{{ summary.overdue_installments }}</div>
                        <div class="stat-label">Cuotas vencidas · interés {{ fmtMoney(summary.overdue_interest) }}</div>
                        <el-icon class="stat-go"><ArrowRight /></el-icon>
                    </div>
                </el-col>
            </el-row>

            <el-row :gutter="16">
                <el-col :xs="24" :lg="12">
                    <el-card shadow="never" class="panel">
                        <template #header>
                            <div class="panel-header"><span>Pagos restantes</span></div>
                        </template>

                        <div v-if="summary.remaining_payments.length" class="pay-legend">
                            <span class="legend-item"><i class="legend-dot legend-dot--red"></i> Vencida</span>
                            <span class="legend-item"><i class="legend-dot legend-dot--orange"></i> Vence en menos de 7 días</span>
                        </div>

                        <el-table
                            v-if="summary.remaining_payments.length"
                            :data="summary.remaining_payments"
                            :row-class-name="payRowClass"
                            size="small"
                        >
                            <el-table-column prop="service_number" label="Servicio" show-overflow-tooltip min-width="110" />
                            <el-table-column prop="label" label="Concepto" show-overflow-tooltip min-width="120" />
                            <el-table-column label="Vence" width="110">
                                <template #default="{ row }">
                                    <span :class="['due-date', dueClass(row)]">{{ fmtDate(row.projected_date) }}</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Total a pagar" align="right" width="120">
                                <template #default="{ row }">{{ fmtMoney(row.total_with_interest) }}</template>
                            </el-table-column>
                            <el-table-column label="" align="right" width="96">
                                <template #default="{ row }">
                                    <el-button type="primary" size="small" @click="startPayment(row)">
                                        Pagar
                                    </el-button>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-else description="No tienes pagos pendientes" :image-size="70" />
                    </el-card>
                </el-col>
                <el-col :xs="24" :lg="12">
                    <el-card shadow="never" class="panel">
                        <template #header>
                            <div class="panel-header-row">
                                <span class="panel-title">Historial de pagos</span>
                                <el-button type="primary" plain size="small" @click="openStatement">
                                    <el-icon><Download /></el-icon>
                                    Ver estado de cuenta
                                </el-button>
                            </div>
                        </template>
                        <el-table :data="payments" size="small">
                            <el-table-column label="Fecha" width="100">
                                <template #default="{ row }">{{ fmtDate(row.payment_date) }}</template>
                            </el-table-column>
                            <el-table-column prop="service_number" label="Servicio" show-overflow-tooltip min-width="120" />
                            <el-table-column label="Monto" align="right" width="110">
                                <template #default="{ row }">{{ fmtMoney(row.amount) }}</template>
                            </el-table-column>
                            <el-table-column prop="notes" label="Notas" min-width="140" show-overflow-tooltip>
                                <template #default="{ row }">
                                    <span v-if="row.notes">{{ row.notes }}</span>
                                    <span v-else class="muted">—</span>
                                </template>
                            </el-table-column>
                            <el-table-column label="Comprobante" width="105" align="center">
                                <template #default="{ row }">
                                    <a v-if="row.receipt_url" :href="row.receipt_url" target="_blank" rel="noopener">
                                        <el-button link type="primary" size="small">Ver</el-button>
                                    </a>
                                    <span v-else class="muted">—</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-if="!payments.length" description="Sin pagos registrados" :image-size="70" />
                    </el-card>
                </el-col>
            </el-row>

            <!-- Modal: abonos en revisión -->
            <el-dialog v-model="reviewVisible" title="Abonos en revisión" width="min(680px, 95vw)">
                <p v-if="summary.pending_review_abonos.length" class="modal-note">
                    Tus abonos están en revisión; se aplicarán a tu saldo cuando la empresa valide el comprobante.
                </p>
                <el-table v-if="summary.pending_review_abonos.length" :data="summary.pending_review_abonos" size="small">
                    <el-table-column label="Fecha pago" width="105">
                        <template #default="{ row }">{{ fmtDate(row.payment_date) }}</template>
                    </el-table-column>
                    <el-table-column prop="service_number" label="Servicio" show-overflow-tooltip />
                    <el-table-column label="Monto" align="right" width="115">
                        <template #default="{ row }">{{ fmtMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column prop="method" label="Método" width="120" />
                    <el-table-column label="Comprobante" width="105" align="center">
                        <template #default="{ row }">
                            <a v-if="row.receipt_url" :href="row.receipt_url" target="_blank" rel="noopener">
                                <el-button link type="primary" size="small">Ver</el-button>
                            </a>
                            <span v-else class="muted">—</span>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-else description="No tienes abonos en revisión" :image-size="70" />
                <template #footer>
                    <el-button @click="reviewVisible = false">Cerrar</el-button>
                </template>
            </el-dialog>

            <!-- Modal: cuotas vencidas -->
            <el-dialog v-model="overdueVisible" title="Cuotas vencidas" width="min(760px, 95vw)">
                <p v-if="summary.overdue_dues.length" class="modal-note">
                    Estas cuotas ya acumulan interés moratorio (10 % mensual). Realiza tu pago para detener el recargo.
                </p>
                <el-table v-if="summary.overdue_dues.length" :data="summary.overdue_dues" size="small">
                    <el-table-column prop="service_number" label="Servicio" show-overflow-tooltip min-width="110" />
                    <el-table-column prop="label" label="Concepto" show-overflow-tooltip min-width="120" />
                    <el-table-column label="Vence" width="100">
                        <template #default="{ row }">{{ fmtDate(row.projected_date) }}</template>
                    </el-table-column>
                    <el-table-column label="Monto" align="right" width="105">
                        <template #default="{ row }">{{ fmtMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column label="Interés" align="right" width="100">
                        <template #default="{ row }">
                            <span :class="{ 'modal-interest': row.interest > 0 }">{{ fmtMoney(row.interest) }}</span>
                        </template>
                    </el-table-column>
                    <el-table-column label="Total" align="right" width="110">
                        <template #default="{ row }"><strong>{{ fmtMoney(row.total_with_interest) }}</strong></template>
                    </el-table-column>
                    <el-table-column label="" align="center" width="100">
                        <template #default="{ row }">
                            <el-button type="primary" size="small" @click="startPayment(row)">
                                Pagar
                            </el-button>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-else description="No tienes cuotas vencidas" :image-size="70" />
                <template #footer>
                    <el-button @click="overdueVisible = false">Cerrar</el-button>
                </template>
            </el-dialog>

            <!-- Registro de pago (componente reutilizable) desde el dashboard -->
            <AbonoDialog
                v-model:visible="payVisible"
                :service-id="payServiceId"
                :service-number="payServiceNumber"
                :payment-method="payPaymentMethod"
                :methods="methods"
                :balance="payBalance"
                :initial-amount="payInitialAmount"
                @success="onPaySuccess"
            />
        </template>

        <el-result
            v-else
            icon="info"
            title="Cuenta sin vincular"
            sub-title="Tu cuenta aún no está vinculada a una ficha de cliente. Contacta a la empresa para completar tu registro."
        />
    </AppLayout>
</template>

<style scoped>
.welcome-banner {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    flex-wrap: wrap;
    background: linear-gradient(120deg, #1e3a8a 0%, #1d4ed8 100%);
    color: #fff;
    border-radius: 14px;
    padding: 22px 26px;
    margin-bottom: 20px;
    box-shadow: 0 12px 24px -12px rgba(30, 58, 138, 0.5);
}

.welcome-banner h2 {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 700;
}

.welcome-banner p {
    margin: 0;
    opacity: 0.9;
}

.welcome-banner :deep(.el-button--primary) {
    background: #facc15;
    border-color: #facc15;
    color: #1e3a8a;
}

.welcome-banner :deep(.el-button--primary:hover) {
    background: #eab308;
    border-color: #eab308;
    color: #1e3a8a;
}

.stat-row {
    margin-bottom: 16px;
}

.stat-row .el-col {
    margin-bottom: 16px;
}

.stat-card {
    background: #fff;
    border-radius: 12px;
    padding: 18px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    border: 1px solid #eef0f3;
    height: 100%;
}

.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: #1e3a8a;
}

.stat-label {
    font-size: 13px;
    color: #64748b;
}

.stat-extra {
    font-size: 12px;
    font-weight: 600;
    color: #b91c1c;
    margin-top: 2px;
}

.panel {
    border-radius: 12px;
    margin-bottom: 16px;
}

.panel-header {
    font-weight: 700;
    color: #1e3a8a;
}

.stat-card.is-clickable {
    cursor: pointer;
    position: relative;
    transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
}

.stat-card.is-clickable:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 24px -14px rgba(30, 58, 138, 0.35);
    border-color: #dbeafe;
}

.stat-card.is-clickable:focus-visible {
    outline: 2px solid #facc15;
    outline-offset: 2px;
}

.stat-go {
    position: absolute;
    top: 16px;
    right: 14px;
    color: #cbd5e1;
}

.stat-card.is-clickable:hover .stat-go {
    color: #facc15;
}

.modal-note {
    margin: 0 0 12px;
    color: #64748b;
    font-size: 13px;
}

.modal-interest {
    color: #b91c1c;
    font-weight: 600;
}

.pay-legend {
    display: flex;
    gap: 16px;
    flex-wrap: wrap;
    margin-bottom: 12px;
    font-size: 12px;
    color: #64748b;
}

.legend-item {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}

.legend-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    display: inline-block;
}

.legend-dot--red {
    background: #dc2626;
}

.legend-dot--orange {
    background: #f59e0b;
}

.due-overdue {
    color: #dc2626;
    font-weight: 600;
}

.due-near {
    color: #ea580c;
    font-weight: 600;
}

.panel :deep(.el-table .pay-row-overdue > td.el-table__cell) {
    background-color: #fef2f2 !important;
}

.panel :deep(.el-table .pay-row-near > td.el-table__cell) {
    background-color: #fffbeb !important;
}

.panel-header-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.panel-title {
    font-weight: 700;
    color: #1e3a8a;
}

.muted {
    color: #94a3b8;
}
</style>
