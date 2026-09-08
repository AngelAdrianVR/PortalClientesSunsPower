<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { fmtMoney } from '@/utils/format';

/**
 * Diálogo de registro de pago/abono (reutilizable: dashboard y detalle de servicio).
 *
 * Flujo por pasos:
 *   1) Monto (precargado si viene de una cuota, pero editable) + método de pago.
 *   2) Referencia y notas (opcionales).
 *   3) Comprobante (obligatorio) — se explica que será revisado antes de aprobar.
 * Se puede retroceder entre pasos.
 */

const props = defineProps({
    visible: { type: Boolean, default: false },
    serviceId: { type: Number, default: null },
    /** Etiqueta del servicio (p. ej. número) para mostrar dentro del diálogo. */
    serviceNumber: { type: String, default: null },
    methods: { type: Array, default: () => [] },
    balance: { type: Number, default: 0 },
    /** Monto sugerido a pre-cargar (cuota a pagar); editable por el usuario. */
    initialAmount: { type: Number, default: null },
});

const emit = defineEmits(['update:visible', 'success']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const currentStep = ref(1);
const fileList = ref([]);

const form = useForm({
    service_order_id: props.serviceId,
    amount: null,
    payment_date: new Date().toISOString().slice(0, 10),
    method: props.methods?.[0] || 'Transferencia',
    reference: '',
    notes: '',
    proof: null,
});

function today() {
    return new Date().toISOString().slice(0, 10);
}

function resetState() {
    form.clearErrors();
    form.reset('amount', 'reference', 'notes', 'proof');
    form.service_order_id = props.serviceId;
    form.payment_date = today();
    form.method = props.methods?.[0] || 'Transferencia';
    currentStep.value = 1;
    fileList.value = [];
}

function prefillAmount() {
    if (props.initialAmount && props.initialAmount > 0 && props.balance > 0) {
        form.amount = Math.min(props.initialAmount, props.balance);
    } else {
        form.amount = null;
    }
}

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            resetState();
            prefillAmount();
        } else {
            currentStep.value = 1;
            form.clearErrors();
        }
    },
    { immediate: true }
);

function onFileChange(uploadFile) {
    const raw = uploadFile?.raw;

    if (!raw) {
        return;
    }

    if (raw.size > 10 * 1024 * 1024) {
        form.errors.proof = 'El comprobante no puede superar 10 MB.';
        fileList.value = [];

        return;
    }

    form.proof = raw;
    delete form.errors.proof;
    fileList.value = [uploadFile];
}

function onFileRemove() {
    form.proof = null;
    fileList.value = [];
}

const disabledDate = (date) => date.getTime() > Date.now();

function validateStep1() {
    let valid = true;

    if (!form.amount || form.amount < 1) {
        form.errors.amount = 'Indica el monto que vas a pagar.';
        valid = false;
    } else {
        delete form.errors.amount;
    }

    if (props.balance > 0 && form.amount > props.balance) {
        form.errors.amount = `El monto no puede superar el saldo pendiente (${fmtMoney(props.balance)}).`;
        valid = false;
    }

    if (!form.method) {
        form.errors.method = 'Selecciona el método de pago.';
        valid = false;
    } else {
        delete form.errors.method;
    }

    if (!form.payment_date) {
        form.errors.payment_date = 'Selecciona la fecha del pago.';
        valid = false;
    } else {
        delete form.errors.payment_date;
    }

    return valid;
}

function goNext() {
    if (currentStep.value === 1 && !validateStep1()) {
        return;
    }

    currentStep.value += 1;
}

function goBack() {
    if (currentStep.value > 1) {
        currentStep.value -= 1;
    }
}

function submit() {
    if (!form.proof) {
        form.errors.proof = 'Sube el comprobante para poder registrar el pago.';

        return;
    }

    delete form.errors.proof;

    form.post(route('portal-payments.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetState();
            emit('success');
        },
    });
}
</script>

<template>
    <el-dialog
        v-model="dialogVisible"
        title="Registrar abono"
        width="min(600px, 95vw)"
        :close-on-click-modal="false"
    >
        <el-steps :active="currentStep - 1" align-center simple class="pay-steps" finish-status="success">
            <el-step title="Monto y método" />
            <el-step title="Referencia y notas" />
            <el-step title="Comprobante" />
        </el-steps>

        <el-form label-position="top" class="abono-form">
            <!-- Paso 1: monto + fecha + método -->
            <div v-if="currentStep === 1" class="step-body">
                <el-alert
                    type="info"
                    :closable="false"
                    class="abono-alert"
                >
                    <template #title>
                        <span v-if="serviceNumber">Servicio: <strong>{{ serviceNumber }}</strong> · </span>
                        Saldo pendiente disponible: <strong>{{ fmtMoney(balance) }}</strong>
                    </template>
                </el-alert>

                <el-form-item label="Monto a pagar" :error="form.errors.amount" required>
                    <el-input-number
                        v-model="form.amount"
                        :min="1"
                        :max="Math.max(balance, 1)"
                        :precision="2"
                        :step="100"
                        :controls-position="'right'"
                        style="width: 100%"
                        placeholder="0.00"
                    />
                    <div class="field-hint">
                        <template v-if="initialAmount && initialAmount > 0">
                            Monto sugerido para esta cuota: {{ fmtMoney(initialAmount) }} (puedes ajustarlo).
                        </template>
                        <template v-else>Indica cuánto vas a abonar a este servicio.</template>
                    </div>
                </el-form-item>

                <el-row :gutter="12">
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="Fecha del pago" :error="form.errors.payment_date" required>
                            <el-date-picker
                                v-model="form.payment_date"
                                type="date"
                                value-format="YYYY-MM-DD"
                                format="DD/MM/YYYY"
                                :disabled-date="disabledDate"
                                style="width: 100%"
                            />
                        </el-form-item>
                    </el-col>
                    <el-col :xs="24" :sm="12">
                        <el-form-item label="Método de pago" :error="form.errors.method" required>
                            <el-select v-model="form.method" style="width: 100%">
                                <el-option v-for="method in methods" :key="method" :label="method" :value="method" />
                            </el-select>
                        </el-form-item>
                    </el-col>
                </el-row>
            </div>

            <!-- Paso 2: referencia + notas -->
            <div v-if="currentStep === 2" class="step-body">
                <el-form-item label="Referencia (opcional)" :error="form.errors.reference">
                    <el-input v-model="form.reference" placeholder="Número de referencia o cheque" maxlength="255" />
                </el-form-item>

                <el-form-item label="Notas (opcional)" :error="form.errors.notes">
                    <el-input v-model="form.notes" type="textarea" :rows="4" maxlength="1000" show-word-limit />
                </el-form-item>
            </div>

            <!-- Paso 3: comprobante -->
            <div v-if="currentStep === 3" class="step-body">
                <el-alert
                    type="warning"
                    :closable="false"
                    show-icon
                    class="review-alert"
                    title="Tu pago quedará en revisión"
                    description="El comprobante será revisado por nuestro equipo antes de aprobar el pago y aplicarlo a tu saldo."
                />

                <el-form-item label="Comprobante del pago (obligatorio)" :error="form.errors.proof" required>
                    <el-upload
                        drag
                        :auto-upload="false"
                        :limit="1"
                        :file-list="fileList"
                        accept=".jpg,.jpeg,.png,.pdf"
                        :on-change="onFileChange"
                        :on-remove="onFileRemove"
                    >
                        <el-icon class="upload-icon"><UploadFilled /></el-icon>
                        <div class="el-upload__text">
                            Arrastra aquí tu comprobante o <em>haz clic para seleccionar</em>
                        </div>
                        <template #tip>
                            <div class="el-upload__tip">JPG, PNG o PDF. Máximo 10 MB.</div>
                        </template>
                    </el-upload>
                </el-form-item>
            </div>
        </el-form>

        <template #footer>
            <el-button @click="dialogVisible = false">Cancelar</el-button>
            <el-button v-if="currentStep > 1" @click="goBack">Atrás</el-button>

            <el-button v-if="currentStep < 3" type="primary" @click="goNext">
                Siguiente
            </el-button>
            <el-button v-else type="primary" :loading="form.processing" @click="submit">
                Enviar abono
            </el-button>
        </template>
    </el-dialog>
</template>

<style scoped>
.pay-steps {
    margin-bottom: 18px;
}

.abono-form {
    margin-top: 4px;
}

.step-body {
    padding-top: 2px;
}

.abono-alert {
    margin-bottom: 16px;
}

.field-hint {
    font-size: 12px;
    color: #94a3b8;
    line-height: 1.4;
    margin-top: 4px;
}

.review-alert {
    margin-bottom: 16px;
}

.upload-icon {
    font-size: 48px;
    color: #94a3b8;
    margin-bottom: 8px;
}
</style>
