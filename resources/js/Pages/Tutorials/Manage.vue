<script setup>
/**
 * Panel de gestión de tutoriales (OCULTO).
 *
 * No hay ningún enlace a esta página en la interfaz: se entra escribiendo
 * `/gestion-tutoriales`. Si el administrador define `TUTORIALS_ADMIN_KEY`, el
 * panel pide la llave una sola vez (`?llave=...`) y la recuerda en sesión.
 */
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { ElMessage } from 'element-plus';
import AppLayout from '@/Layouts/AppLayout.vue';

const props = defineProps({
    tutorials: { type: Array, default: () => [] },
    limits: { type: Object, required: true },
    acceptedExtensions: { type: Array, default: () => [] },
    locked: { type: Boolean, default: false },
    oversized: { type: Boolean, default: false },
});

const form = useForm({
    title: '',
    description: '',
    kind: 'file',
    url: '',
    sort_order: 0,
    file: null,
    poster: null,
});

const fileLabel = ref('');
const posterLabel = ref('');
const deletingId = ref(null);

/** extensions → ".mp4,.pdf,…" para el atributo accept del selector. */
const accept = computed(() => props.acceptedExtensions.map((extension) => `.${extension}`).join(','));

/** Límite real por archivo (el menor entre el portal y php.ini). */
const maxUploadMb = computed(() => props.limits.effective_mb);

/** Tope del archivo en bytes, para avisar antes de subir nada. */
const maxUploadBytes = computed(() => maxUploadMb.value * 1048576);

/** URL completa del panel, para copiarla y volver a entrar cuando se necesite. */
const panelUrl = computed(() => `${window.location.origin}${window.location.pathname}`);

/** Al cambiar el tipo se limpia lo que ya no aplica (archivo o enlace). */
watch(() => form.kind, () => {
    form.file = null;
    form.url = '';
    fileLabel.value = '';
    form.clearErrors();
});

function onFileChange(uploadFile) {
    // Se avisa ANTES de subir: mandar 300 MB para recibir un error es una
    // pérdida de tiempo y de datos del cliente.
    if (uploadFile.size > maxUploadBytes.value) {
        ElMessage.error(
            `El archivo pesa ${(uploadFile.size / 1048576).toFixed(1)} MB y el máximo es ${maxUploadMb.value} MB. ` +
            'Recomprime el video (720p, ~1 Mbps) o agrégalo como enlace de YouTube/Vimeo.'
        );
        form.file = null;
        fileLabel.value = '';

        return;
    }

    form.file = uploadFile.raw;
    form.clearErrors('file');
    fileLabel.value = `${uploadFile.name} · ${(uploadFile.size / 1048576).toFixed(1)} MB`;
}

function onPosterChange(uploadFile) {
    if (uploadFile.size > 4 * 1048576) {
        ElMessage.error('La portada no puede pesar más de 4 MB.');
        form.poster = null;
        posterLabel.value = '';

        return;
    }

    form.poster = uploadFile.raw;
    posterLabel.value = uploadFile.name;
}

function submit() {
    form.post(route('tutorials.store'), {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            fileLabel.value = '';
            posterLabel.value = '';
        },
    });
}

function remove(tutorial) {
    deletingId.value = tutorial.id;

    router.delete(route('tutorials.destroy', { tutorial: tutorial.id }), {
        preserveScroll: true,
        onFinish: () => {
            deletingId.value = null;
        },
    });
}

function copyPanelUrl() {
    navigator.clipboard?.writeText(panelUrl.value);
    ElMessage.success('URL del panel copiada.');
}

/** Abre el tutorial en otra pestaña (video o archivo), sin salir del panel. */
function openTutorial(tutorial) {
    window.open(tutorial.src, '_blank', 'noopener');
}

/** Detalle secundario de cada fila del listado. */
function rowSub(tutorial) {
    if (tutorial.kind === 'link') {
        return 'Enlace externo';
    }

    return tutorial.original_name || '';
}
</script>

<template>
    <Head title="Gestión de tutoriales" />

    <AppLayout title="Gestión de tutoriales">
        <!-- El servidor rechazó la subida por tamaño (ver bootstrap/app.php) -->
        <el-alert v-if="oversized" type="error" :closable="false" show-icon class="manage-alert">
            <template #title>El archivo es más grande de lo que acepta el servidor</template>
            El límite actual es de {{ maxUploadMb }} MB por archivo. Recomprime el video (720p, ~1 Mbps) antes de subirlo
            o agrégalo como enlace de YouTube/Vimeo, que no consume espacio del portal.
        </el-alert>

        <!-- Aviso: esta página es privada y no está enlazada en la interfaz -->
        <el-alert type="warning" :closable="false" show-icon class="manage-alert">
            <template #title>Panel privado (no aparece en el menú)</template>
            <div class="manage-alert-body">
                <span>
                    Esta página no tiene enlace en el portal: solo se entra escribiendo su URL.
                    Desde aquí puedes agregar o quitar los video tutoriales que ven tus clientes.
                </span>
                <div class="manage-url">
                    <code>{{ panelUrl }}</code>
                    <el-button size="small" text type="primary" @click="copyPanelUrl">
                        <el-icon class="mr-1"><CopyDocument /></el-icon>
                        Copiar
                    </el-button>
                </div>
                <span v-if="locked" class="manage-note">
                    <el-icon><Lock /></el-icon>
                    Protegido con llave: cualquiera que entre debe conocerla.
                </span>
                <span v-else class="manage-note">
                    <el-icon><InfoFilled /></el-icon>
                    Sin llave: cualquier cliente que escriba esta URL puede administrar los tutoriales.
                    Para bloquearlo, define <code>TUTORIALS_ADMIN_KEY</code> en el .env.
                </span>
            </div>
        </el-alert>

        <el-row :gutter="16">
            <!-- Alta de tutorial -->
            <el-col :xs="24" :lg="10" class="manage-col">
                <el-card shadow="never" class="manage-card">
                    <template #header>
                        <div class="card-header">
                            <el-icon><Plus /></el-icon>
                            <span>Agregar tutorial</span>
                        </div>
                    </template>

                    <el-form label-position="top" @submit.prevent>
                        <el-form-item label="Título" :error="form.errors.title">
                            <el-input v-model="form.title" maxlength="120" placeholder="Cómo registrar un pago" />
                        </el-form-item>

                        <el-form-item label="Descripción (opcional)" :error="form.errors.description">
                            <el-input
                                v-model="form.description"
                                type="textarea"
                                :rows="2"
                                maxlength="500"
                                placeholder="Paso a paso para subir un comprobante."
                            />
                        </el-form-item>

                        <el-form-item label="Tipo" :error="form.errors.kind">
                            <el-radio-group v-model="form.kind">
                                <el-radio-button value="file">Subir archivo</el-radio-button>
                                <el-radio-button value="link">Enlace (YouTube / URL)</el-radio-button>
                            </el-radio-group>
                        </el-form-item>

                        <!-- Subida de archivo -->
                        <template v-if="form.kind === 'file'">
                            <el-form-item :error="form.errors.file">
                                <el-upload
                                    drag
                                    :auto-upload="false"
                                    :show-file-list="false"
                                    :accept="accept"
                                    :on-change="onFileChange"
                                >
                                    <el-icon class="el-icon--upload"><UploadFilled /></el-icon>
                                    <div class="el-upload__text">
                                        Arrastra el video aquí o <em>haz clic para elegirlo</em>
                                    </div>
                                    <template #tip>
                                        <div class="el-upload__tip">
                                            Máx. {{ maxUploadMb }} MB · recomendado: recomprimir el video (720p, ~1 Mbps) antes de subirlo.
                                        </div>
                                    </template>
                                </el-upload>
                                <div v-if="fileLabel" class="file-label">
                                    <el-icon><VideoPlay /></el-icon>
                                    {{ fileLabel }}
                                </div>
                            </el-form-item>

                            <el-form-item label="Portada (opcional)" :error="form.errors.poster">
                                <el-upload
                                    :auto-upload="false"
                                    :show-file-list="false"
                                    accept=".jpg,.jpeg,.png,.webp"
                                    :on-change="onPosterChange"
                                >
                                    <el-button plain>
                                        <el-icon class="mr-1"><Picture /></el-icon>
                                        Elegir imagen
                                    </el-button>
                                </el-upload>
                                <div v-if="posterLabel" class="file-label">{{ posterLabel }}</div>
                                <div class="field-hint">
                                    Se muestra en la tarjeta sin descargar el video (jpg/png/webp, máx. 4 MB).
                                </div>
                            </el-form-item>
                        </template>

                        <!-- Enlace externo -->
                        <el-form-item v-else label="URL del video o archivo" :error="form.errors.url">
                            <el-input v-model="form.url" placeholder="https://www.youtube.com/watch?v=..." />
                            <div class="field-hint">
                                Acepta YouTube, Vimeo o un enlace directo a .mp4/.pdf. Ideal para videos pesados:
                                se ven sin consumir espacio ni datos del portal.
                            </div>
                        </el-form-item>

                        <el-form-item label="Orden (opcional)" :error="form.errors.sort_order">
                            <el-input-number v-model="form.sort_order" :min="0" :max="9999" />
                            <div class="field-hint">Menor número aparece primero.</div>
                        </el-form-item>

                        <el-progress
                            v-if="form.progress"
                            :percentage="form.progress.percentage"
                            :stroke-width="14"
                            class="upload-progress"
                        />

                        <el-button
                            type="primary"
                            class="submit-btn"
                            :loading="form.processing"
                            @click="submit"
                        >
                            <el-icon class="mr-1"><Upload /></el-icon>
                            Guardar tutorial
                        </el-button>
                    </el-form>
                </el-card>
            </el-col>

            <!-- Listado -->
            <el-col :xs="24" :lg="14" class="manage-col">
                <el-card shadow="never" class="manage-card">
                    <template #header>
                        <div class="card-header">
                            <el-icon><Collection /></el-icon>
                            <span>Tutoriales publicados ({{ tutorials.length }})</span>
                        </div>
                    </template>

                    <el-table v-if="tutorials.length" :data="tutorials" stripe>
                        <el-table-column label="Título" min-width="180">
                            <template #default="{ row }">
                                <div class="row-title">{{ row.title }}</div>
                                <div class="row-sub">{{ rowSub(row) }}</div>
                            </template>
                        </el-table-column>

                        <el-table-column label="Tipo" width="110">
                            <template #default="{ row }">
                                <el-tag size="small" effect="plain">
                                    {{ row.media_type === 'document' ? 'Archivo' : 'Video' }}
                                </el-tag>
                            </template>
                        </el-table-column>

                        <el-table-column label="Tamaño" width="100">
                            <template #default="{ row }">{{ row.size_human || '—' }}</template>
                        </el-table-column>

                        <el-table-column prop="sort_order" label="Orden" width="80" />

                        <el-table-column label="Acciones" width="150" align="right">
                            <template #default="{ row }">
                                <el-button size="small" text type="primary" @click="openTutorial(row)">
                                    <el-icon><View /></el-icon>
                                </el-button>
                                <el-popconfirm
                                    title="¿Eliminar este tutorial? Se borra también el archivo."
                                    confirm-button-text="Sí, eliminar"
                                    cancel-button-text="Cancelar"
                                    @confirm="remove(row)"
                                >
                                    <template #reference>
                                        <el-button
                                            size="small"
                                            text
                                            type="danger"
                                            :loading="deletingId === row.id"
                                        >
                                            <el-icon><Delete /></el-icon>
                                        </el-button>
                                    </template>
                                </el-popconfirm>
                            </template>
                        </el-table-column>
                    </el-table>

                    <el-empty v-else description="Aún no hay tutoriales. Agrega el primero con el formulario." />
                </el-card>
            </el-col>
        </el-row>
    </AppLayout>
</template>

<style scoped>
.manage-alert {
    margin-bottom: 16px;
}

.manage-alert-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
    font-size: 13px;
}

.manage-url {
    display: flex;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}

.manage-url code {
    padding: 3px 8px;
    font-size: 12px;
    color: #1e3a8a;
    background: #eef2ff;
    border-radius: 6px;
    word-break: break-all;
}

.manage-note {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: #92400e;
}

.manage-note code {
    padding: 1px 5px;
    font-size: 11px;
    background: #fef3c7;
    border-radius: 4px;
}

.manage-col {
    margin-bottom: 16px;
}

.manage-card {
    height: 100%;
}

.card-header {
    display: flex;
    align-items: center;
    gap: 8px;
    font-weight: 700;
    color: #1e3a8a;
}

.file-label {
    margin-top: 8px;
    font-size: 12px;
    color: #15803d;
    word-break: break-all;
}

.field-hint {
    margin-top: 4px;
    font-size: 12px;
    color: #94a3b8;
    line-height: 1.4;
}

.upload-progress {
    margin-bottom: 12px;
}

.submit-btn {
    width: 100%;
}

.row-title {
    font-weight: 600;
    color: #1e3a8a;
}

.row-sub {
    font-size: 12px;
    color: #94a3b8;
    word-break: break-all;
}
</style>
