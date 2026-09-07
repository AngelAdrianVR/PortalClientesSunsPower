<script setup>
import { computed } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import { fmtMoney, fmtDate } from '@/utils/format';

const props = defineProps({
    services: { type: Array, default: () => [] },
    clientBalance: { type: Number, default: 0 },
});

const page = usePage();
const portalClient = computed(() => page.props.portalClient);

function statusTag(status) {
    if (status === 'Completado') return 'success';
    if (status === 'Facturado') return 'info';

    return 'primary';
}
</script>

<template>
    <Head title="Servicios" />

    <AppLayout title="Servicios">
        <div class="services-header">
            <div>
                <h2 class="services-title">Mis servicios contratados</h2>
                <p class="services-subtitle">
                    Saldo pendiente total:
                    <strong>{{ fmtMoney(clientBalance) }}</strong>
                </p>
            </div>
        </div>

        <el-row v-if="services.length" :gutter="16">
            <el-col v-for="service in services" :key="service.id" :xs="24" :sm="12" :lg="8" class="service-col">
                <el-card shadow="hover" class="service-card" @click="router.visit(route('services.show', service.id))">
                    <div class="service-top">
                        <div>
                            <div class="service-number">{{ service.service_number }}</div>
                            <div class="service-type">{{ service.system_type || 'Sistema solar' }}</div>
                        </div>
                        <el-tag :type="statusTag(service.status)" size="small">{{ service.status }}</el-tag>
                    </div>

                    <div class="service-info">
                        <div class="info-row">
                            <el-icon color="#64748b"><Calendar /></el-icon>
                            <span>Inicio: {{ fmtDate(service.start_date) }}</span>
                        </div>
                        <div class="info-row">
                            <el-icon color="#64748b"><Location /></el-icon>
                            <span class="ellipsis">{{ service.installation_address }}</span>
                        </div>
                        <div class="info-row">
                            <el-icon color="#64748b"><CreditCard /></el-icon>
                            <span>Plan: {{ service.payment_method || '—' }}</span>
                        </div>
                    </div>

                    <el-divider class="service-divider" />

                    <div class="service-amounts">
                        <div>
                            <div class="amount-label">Costo total</div>
                            <div class="amount-value">{{ fmtMoney(service.total_amount) }}</div>
                        </div>
                        <div class="amount-right">
                            <div class="amount-label">Saldo pendiente</div>
                            <div class="amount-value" :class="{ 'amount-debt': service.balance > 0 }">
                                {{ fmtMoney(service.balance) }}
                            </div>
                        </div>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-empty
            v-else
            description="Aún no tienes servicios contratados visibles. Si crees que es un error, contacta a la empresa."
        />
    </AppLayout>
</template>

<style scoped>
.services-header {
    margin-bottom: 20px;
}

.services-title {
    margin: 0 0 4px;
    font-size: 18px;
    font-weight: 700;
    color: #0f172a;
}

.services-subtitle {
    margin: 0;
    color: #64748b;
}

.service-col {
    margin-bottom: 16px;
}

.service-card {
    border-radius: 12px;
    cursor: pointer;
}

.service-top {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 12px;
}

.service-number {
    font-weight: 700;
    color: #0f172a;
}

.service-type {
    font-size: 13px;
    color: #64748b;
}

.service-info {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.info-row {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 13px;
    color: #475569;
}

.ellipsis {
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.service-divider {
    margin: 14px 0;
}

.service-amounts {
    display: flex;
    justify-content: space-between;
    gap: 12px;
}

.amount-right {
    text-align: right;
}

.amount-label {
    font-size: 12px;
    color: #94a3b8;
}

.amount-value {
    font-weight: 700;
    color: #0f172a;
}

.amount-debt {
    color: #0ea5e9;
}
</style>
