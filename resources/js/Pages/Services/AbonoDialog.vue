<script setup>
import { computed, ref, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { fmtMoney } from '@/utils/format';

const props = defineProps({
    visible: { type: Boolean, default: false },
    serviceId: { type: Number, required: true },
    methods: { type: Array, default: () => [] },
    balance: { type: Number, default: 0 },
});

const emit = defineEmits(['update:visible', 'success']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => emit('update:visible', value),
});

const form = useForm({
    service_order_id: props.serviceId,
    amount: null,
    payment_date: new Date().toISOString().slice(0, 10),
    method: 'Transferencia',
    reference: '',
    notes: '',
    proof: null,
});

const fileList = ref([]);

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

function submit() {
    form.post(route('portal-payments.store'), {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            resetForm();
            emit('success');
        },
    });
}

function resetForm() {
    form.reset('amount', 'reference', 'notes', 'proof');
    form.payment_date = new Date().toISOString().slice(0, 10);
    fileList.value = [];
    form.clearErrors();
}

watch(
    () => props.visible,
    (visible) => {
        if (!visible) {
            form.clearErrors();
        }
    }
);
</script>

<template>
    <el-dialog
        v-model="dialogVisible"
        title="Registrar abono"
        width="min(560px, 95vw)"
        :close-on-click-modal="false"
        destroy-on-close
    >
        <el-alert
            type="info"
            :closable="false"
            class="abono-alert"
            :title="`Saldo pendiente disponible: ${fmtMoney(balance)}`"
            description="Tu abono quedará en revisión hasta que la empresa valide el comprobante y confirme el pago."
        />

        <el-form label-position="top" class="abono-form">
            <el-form-item label="Monto del abono" :error="form.errors.amount" required>
                <el-input-number
                    v-model="form.amount"
                    :min="1"
                    :max="balance"
                    :precision="2"
                    :step="100"
                    controls-position="right"
                    style="width: 100%"
                />
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

            <el-form-item label="Referencia (opcional)" :error="form.errors.reference">
                <el-input v-model="form.reference" placeholder="Número de referencia o cheque" maxlength="255" />
            </el-form-item>

            <el-form-item label="Notas (opcional)" :error="form.errors.notes">
                <el-input v-model="form.notes" type="textarea" :rows="2" maxlength="1000" show-word-limit />
            </el-form-item>

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
        </el-form>

        <template #footer>
            <el-button @click="dialogVisible = false">Cancelar</el-button>
            <el-button type="primary" :loading="form.processing" @click="submit">
                Enviar abono
            </el-button>
        </template>
    </el-dialog>
</template>

<style scoped>
.abono-alert {
    margin-bottom: 16px;
}

.abono-form {
    margin-top: 4px;
}

.upload-icon {
    font-size: 48px;
    color: #94a3b8;
    margin-bottom: 8px;
}
</style>
