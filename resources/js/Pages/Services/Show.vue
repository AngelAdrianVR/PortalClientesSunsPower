<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AbonoDialog from './AbonoDialog.vue';
import { fmtMoney, fmtDate } from '@/utils/format';

const props = defineProps({
    service: { type: Object, required: true },
    client: { type: Object, required: true },
    installments: { type: Array, default: () => [] },
    payments: { type: Array, default: () => [] },
    abonos: { type: Array, default: () => [] },
    balance: { type: Number, default: 0 },
    methods: { type: Array, default: () => [] },
});

const activeTab = ref('info');
const abonoVisible = ref(false);

const totalPaid = computed(() => Math.max(0, props.service.total_amount - props.balance));
const overdueInterest = computed(() => props.installments.reduce((sum, i) => sum + Number(i.interest || 0), 0));

function statusTag(status) {
    if (status === 'Completado') return 'success';
    if (status === 'Facturado') return 'info';

    return 'primary';
}

function installmentMeta(status) {
    switch (status) {
        case 'paid':
            return { type: 'success', label: 'Pagada' };
        case 'on_time':
            return { type: 'success', label: 'Pagada a tiempo' };
        case 'upcoming':
            return { type: 'info', label: 'Por vencer' };
        case 'pending':
            return { type: 'warning', label: 'Pendiente' };
        case 'late':
            return { type: 'warning', label: 'Vencida' };
        default:
            return { type: 'danger', label: 'Vencida +10 días' };
    }
}

function abonoTag(status) {
    if (status === 'Completado') return { type: 'success', label: 'Completado' };
    if (status === 'Rechazado') return { type: 'danger', label: 'Rechazado' };

    return { type: 'warning', label: 'En revisión' };
}

function onAbonoSuccess() {
    abonoVisible.value = false;
    router.reload({ only: ['payments', 'abonos', 'balance'], preserveScroll: true });
}
</script>

<template>
    <Head :title="service.service_number" />

    <AppLayout :title="service.service_number">
        <div class="show-header">
            <div class="show-header-left">
                <Link :href="route('services.index')" class="back-link">
                    <el-icon><ArrowLeft /></el-icon>
                    Volver a servicios
                </Link>
                <div class="show-title-row">
                    <h2 class="show-title">{{ service.service_number }}</h2>
                    <el-tag :type="statusTag(service.status)" size="small">{{ service.status }}</el-tag>
                </div>
            </div>

            <a :href="route('services.statement', service.id)" class="statement-link">
                <el-button type="primary" plain>
                    <el-icon><Download /></el-icon>
                    Descargar estado de cuenta
                </el-button>
            </a>
        </div>

        <el-alert
            v-if="balance > 0"
            type="info"
            show-icon
            :closable="false"
            class="balance-alert"
        >
            <template #title>
                Saldo pendiente de este servicio: <strong>{{ fmtMoney(balance) }}</strong>
            </template>
            Puedes registrar abonos desde la pestaña Pagos; quedarán en revisión hasta que la empresa valide el comprobante.
        </el-alert>

        <el-card shadow="never" class="panel">
            <el-tabs v-model="activeTab">
                <el-tab-pane label="Información" name="info">
                    <el-descriptions :column="2" border size="default" class="info-desc">
                        <el-descriptions-item label="Cliente">{{ client.name }}</el-descriptions-item>
                        <el-descriptions-item label="RFC">{{ client.tax_id || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="Tipo de sistema">{{ service.system_type || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="Tarifa CFE">{{ service.rate_type || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="Fecha de inicio">{{ fmtDate(service.start_date) }}</el-descriptions-item>
                        <el-descriptions-item label="Fecha de término">{{ fmtDate(service.completion_date) }}</el-descriptions-item>
                        <el-descriptions-item label="Plan de pago">{{ service.payment_method || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="Anticipo">{{ fmtMoney(service.down_payment) }}</el-descriptions-item>
                        <el-descriptions-item label="Capacidad total">{{ service.total_capacity ? `${service.total_capacity} kW` : '—' }}</el-descriptions-item>
                        <el-descriptions-item label="Paneles">{{ service.number_of_units ? `${service.number_of_units} módulos de ${service.unit_capacity} W` : '—' }}</el-descriptions-item>
                        <el-descriptions-item label="Voltaje">{{ service.voltage || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="Número de medidor">{{ service.meter_number || '—' }}</el-descriptions-item>
                        <el-descriptions-item label="Dirección de instalación" :span="2">
                            {{ service.installation_address }}
                        </el-descriptions-item>
                        <el-descriptions-item label="Contrato">
                            <template v-if="service.contract">
                                <el-tag size="small" :type="service.contract.status === 'Firmado' ? 'success' : 'info'">
                                    {{ service.contract.status }}
                                </el-tag>
                                <a
                                    v-if="service.contract.signed_url"
                                    :href="service.contract.signed_url"
                                    target="_blank"
                                    class="contract-link"
                                >
                                    Ver contrato firmado
                                </a>
                                <span v-else class="muted"> — aún no firmado</span>
                            </template>
                            <span v-else class="muted">No disponible</span>
                        </el-descriptions-item>
                        <el-descriptions-item label="Costo total">
                            <strong>{{ fmtMoney(service.total_amount) }}</strong>
                        </el-descriptions-item>
                    </el-descriptions>
                </el-tab-pane>

                <el-tab-pane label="Estado de cuenta" name="statement">
                    <el-table :data="installments" size="default" class="statement-table">
                        <el-table-column prop="installment_number" label="#" width="50" />
                        <el-table-column prop="label" label="Concepto" min-width="160" show-overflow-tooltip />
                        <el-table-column label="Vencimiento" width="120">
                            <template #default="{ row }">{{ fmtDate(row.projected_date) }}</template>
                        </el-table-column>
                        <el-table-column label="Monto" align="right" width="130">
                            <template #default="{ row }">{{ fmtMoney(row.amount) }}</template>
                        </el-table-column>
                        <el-table-column label="Estatus" width="150">
                            <template #default="{ row }">
                                <el-tag :type="installmentMeta(row.status).type" size="small">
                                    {{ installmentMeta(row.status).label }}
                                </el-tag>
                            </template>
                        </el-table-column>
                        <el-table-column label="Interés" align="right" width="120">
                            <template #default="{ row }">
                                <span :class="{ 'interest-positive': row.interest > 0 }">{{ fmtMoney(row.interest) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column label="Total a pagar" align="right" width="140">
                            <template #default="{ row }">{{ fmtMoney(row.total_with_interest) }}</template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-if="!installments.length" description="Este servicio no tiene cuotas registradas" />

                    <div class="statement-totals">
                        <div class="total-item">
                            <span class="total-label">Costo total</span>
                            <span class="total-value">{{ fmtMoney(service.total_amount) }}</span>
                        </div>
                        <div class="total-item">
                            <span class="total-label">Pagado a capital</span>
                            <span class="total-value">{{ fmtMoney(totalPaid) }}</span>
                        </div>
                        <div class="total-item">
                            <span class="total-label">Saldo pendiente</span>
                            <span class="total-value balance-value">{{ fmtMoney(balance) }}</span>
                        </div>
                        <div class="total-item" v-if="overdueInterest > 0">
                            <span class="total-label">Interés moratorio acumulado</span>
                            <span class="total-value interest-total">{{ fmtMoney(overdueInterest) }}</span>
                        </div>
                    </div>

                    <a :href="route('services.statement', service.id)">
                        <el-button type="primary" plain>
                            <el-icon><Download /></el-icon>
                            Descargar PDF
                        </el-button>
                    </a>
                </el-tab-pane>

                <el-tab-pane name="payments">
                    <template #label>
                        Pagos
                        <el-badge v-if="abonos.length" :value="abonos.length" class="tab-badge" />
                    </template>

                    <div class="payments-actions">
                        <el-tooltip :disabled="balance > 0" content="No tienes saldo pendiente para abonar" placement="top">
                            <span>
                                <el-button type="primary" :disabled="balance <= 0" @click="abonoVisible = true">
                                    <el-icon><Plus /></el-icon>
                                    Registrar abono
                                </el-button>
                            </span>
                        </el-tooltip>
                    </div>

                    <h3 class="section-title">Abonos registrados desde el portal</h3>
                    <el-table :data="abonos" size="small">
                        <el-table-column label="Registrado" width="140">
                            <template #default="{ row }">{{ row.created_at }}</template>
                        </el-table-column>
                        <el-table-column label="Fecha del pago" width="120">
                            <template #default="{ row }">{{ fmtDate(row.payment_date) }}</template>
                        </el-table-column>
                        <el-table-column label="Monto" align="right" width="120">
                            <template #default="{ row }">{{ fmtMoney(row.amount) }}</template>
                        </el-table-column>
                        <el-table-column prop="method" label="Método" width="120" />
                        <el-table-column prop="reference" label="Referencia" min-width="120" show-overflow-tooltip />
                        <el-table-column label="Estatus" width="130">
                            <template #default="{ row }">
                                <el-tag :type="abonoTag(row.status).type" size="small">
                                    {{ abonoTag(row.status).label }}
                                </el-tag>
                                <el-tooltip v-if="row.rejection_reason" :content="row.rejection_reason" placement="top">
                                    <el-icon class="reject-icon" color="#ef4444"><QuestionFilled /></el-icon>
                                </el-tooltip>
                            </template>
                        </el-table-column>
                        <el-table-column label="Comprobante" width="110" align="center">
                            <template #default="{ row }">
                                <a :href="route('media.download', row.receipt_id)" target="_blank">
                                    <el-button link type="primary" size="small">Ver</el-button>
                                </a>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!abonos.length" description="Aún no registras abonos" :image-size="70" />

                    <h3 class="section-title">Pagos validados</h3>
                    <el-table :data="payments" size="small">
                        <el-table-column label="Fecha" width="120">
                            <template #default="{ row }">{{ fmtDate(row.payment_date) }}</template>
                        </el-table-column>
                        <el-table-column label="Monto" align="right" width="120">
                            <template #default="{ row }">{{ fmtMoney(row.amount) }}</template>
                        </el-table-column>
                        <el-table-column label="Interés" align="right" width="110">
                            <template #default="{ row }">
                                <span class="muted">{{ fmtMoney(row.interest_amount) }}</span>
                            </template>
                        </el-table-column>
                        <el-table-column prop="method" label="Método" width="120" />
                        <el-table-column prop="reference" label="Referencia" min-width="120" show-overflow-tooltip />
                        <el-table-column label="Comprobante" width="110" align="center">
                            <template #default="{ row }">
                                <a v-if="row.receipt_id" :href="route('media.download', row.receipt_id)" target="_blank">
                                    <el-button link type="primary" size="small">Ver</el-button>
                                </a>
                                <span v-else class="muted">—</span>
                            </template>
                        </el-table-column>
                    </el-table>
                    <el-empty v-if="!payments.length" description="Sin pagos validados" :image-size="70" />
                </el-tab-pane>
            </el-tabs>
        </el-card>

        <AbonoDialog
            v-model:visible="abonoVisible"
            :service-id="service.id"
            :methods="methods"
            :balance="balance"
            @success="onAbonoSuccess"
        />
    </AppLayout>
</template>

<style scoped>
.show-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.show-header-left {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.back-link {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    color: #64748b;
    font-size: 13px;
    text-decoration: none;
}

.back-link:hover {
    color: #10b981;
}

.show-title-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.show-title {
    margin: 0;
    font-size: 22px;
    font-weight: 700;
    color: #0f172a;
}

.balance-alert {
    margin-bottom: 16px;
}

.panel {
    border-radius: 12px;
}

.info-desc :deep(.el-descriptions__label) {
    font-weight: 600;
}

.contract-link {
    margin-left: 10px;
    color: #0ea5e9;
    font-size: 13px;
}

.statement-table {
    margin-bottom: 16px;
}

.interest-positive {
    color: #b91c1c;
    font-weight: 600;
}

.statement-totals {
    display: flex;
    flex-direction: column;
    gap: 6px;
    max-width: 360px;
    margin-bottom: 16px;
}

.total-item {
    display: flex;
    justify-content: space-between;
}

.total-label {
    color: #64748b;
}

.total-value {
    font-weight: 600;
    color: #0f172a;
}

.balance-value {
    color: #0ea5e9;
}

.interest-total {
    color: #b91c1c;
}

.payments-actions {
    margin-bottom: 18px;
}

.section-title {
    font-size: 15px;
    font-weight: 600;
    color: #0f172a;
    margin: 18px 0 10px;
}

.tab-badge {
    margin-left: 6px;
}

.reject-icon {
    margin-left: 6px;
    vertical-align: middle;
    cursor: help;
}

.muted {
    color: #94a3b8;
}
</style>
