<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fmtMoney, fmtDate } from '@/utils/format';

const props = defineProps({
    summary: { type: Object, default: null },
    recentPayments: { type: Array, default: () => [] },
    recentAbonos: { type: Array, default: () => [] },
});

const page = usePage();
const portalClient = computed(() => page.props.portalClient);
const linked = computed(() => Boolean(portalClient.value));

function abonoTag(status) {
    if (status === 'Completado') return { type: 'success', label: 'Completado' };
    if (status === 'Rechazado') return { type: 'danger', label: 'Rechazado' };
    return { type: 'warning', label: 'En revisión' };
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
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :lg="6">
                    <div class="stat-card">
                        <el-icon :size="30" color="#eab308"><Clock /></el-icon>
                        <div class="stat-value">{{ summary.pending_review_count }}</div>
                        <div class="stat-label">Abonos en revisión · {{ fmtMoney(summary.pending_review_total) }}</div>
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :lg="6">
                    <div class="stat-card">
                        <el-icon :size="30" color="#1e3a8a"><Grid /></el-icon>
                        <div class="stat-value">{{ summary.services_count }}</div>
                        <div class="stat-label">Servicios contratados</div>
                    </div>
                </el-col>
                <el-col :xs="24" :sm="12" :lg="6">
                    <div class="stat-card">
                        <el-icon :size="30" color="#ef4444"><Warning /></el-icon>
                        <div class="stat-value">{{ summary.overdue_installments }}</div>
                        <div class="stat-label">Cuotas vencidas · interés {{ fmtMoney(summary.overdue_interest) }}</div>
                    </div>
                </el-col>
            </el-row>

            <el-row :gutter="16">
                <el-col :xs="24" :lg="12">
                    <el-card shadow="never" class="panel">
                        <template #header>
                            <div class="panel-header"><span>Próximo vencimiento</span></div>
                        </template>
                        <div v-if="summary.next_due" class="next-due">
                            <div class="next-due-label">{{ summary.next_due.label }}</div>
                            <div class="next-due-date">Vence el {{ fmtDate(summary.next_due.projected_date) }}</div>
                            <div class="next-due-amount">{{ fmtMoney(summary.next_due.amount) }}</div>
                        </div>
                        <el-empty v-else description="Sin pagos próximos" :image-size="70" />
                    </el-card>
                </el-col>
                <el-col :xs="24" :lg="12">
                    <el-card shadow="never" class="panel">
                        <template #header>
                            <div class="panel-header"><span>Últimos pagos</span></div>
                        </template>
                        <el-table :data="recentPayments" size="small">
                            <el-table-column label="Fecha" width="110">
                                <template #default="{ row }">{{ fmtDate(row.payment_date) }}</template>
                            </el-table-column>
                            <el-table-column prop="service_number" label="Servicio" show-overflow-tooltip />
                            <el-table-column label="Monto" align="right" width="130">
                                <template #default="{ row }">{{ fmtMoney(row.amount) }}</template>
                            </el-table-column>
                            <el-table-column label="Comprobante" width="110" align="center">
                                <template #default="{ row }">
                                    <a v-if="row.receipt_id" :href="route('media.download', row.receipt_id)" target="_blank">
                                        <el-button link type="primary" size="small">Ver</el-button>
                                    </a>
                                    <span v-else class="muted">—</span>
                                </template>
                            </el-table-column>
                        </el-table>
                        <el-empty v-if="!recentPayments.length" description="Sin pagos registrados" :image-size="70" />
                    </el-card>
                </el-col>
            </el-row>

            <el-card shadow="never" class="panel">
                <template #header>
                    <div class="panel-header"><span>Mis abonos recientes</span></div>
                </template>
                <el-table :data="recentAbonos" size="small">
                    <el-table-column label="Fecha" width="110">
                        <template #default="{ row }">{{ fmtDate(row.payment_date) }}</template>
                    </el-table-column>
                    <el-table-column prop="service_number" label="Servicio" show-overflow-tooltip />
                    <el-table-column label="Monto" align="right" width="130">
                        <template #default="{ row }">{{ fmtMoney(row.amount) }}</template>
                    </el-table-column>
                    <el-table-column label="Estatus" width="140" align="center">
                        <template #default="{ row }">
                            <el-tag :type="abonoTag(row.status).type" size="small">{{ abonoTag(row.status).label }}</el-tag>
                        </template>
                    </el-table-column>
                </el-table>
                <el-empty v-if="!recentAbonos.length" description="Aún no registras abonos" :image-size="70" />
            </el-card>
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

.panel {
    border-radius: 12px;
    margin-bottom: 16px;
}

.panel-header {
    font-weight: 700;
    color: #1e3a8a;
}

.next-due {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.next-due-label {
    font-weight: 600;
    color: #0f172a;
}

.next-due-date {
    color: #64748b;
    font-size: 13px;
}

.next-due-amount {
    font-size: 20px;
    font-weight: 700;
    color: #1e3a8a;
}

.muted {
    color: #94a3b8;
}
</style>
