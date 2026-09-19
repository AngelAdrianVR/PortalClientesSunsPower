<script setup>
/**
 * Sección "Tutoriales" del portal (visible para todos los clientes).
 *
 * El listado lo arma el backend; aquí solo se muestran las tarjetas y, al
 * elegir una, se abre el reproductor (componente que se carga bajo demanda).
 */
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import TutorialDialog from '@/Components/TutorialDialog.vue';

defineProps({
    tutorials: { type: Array, default: () => [] },
});

const active = ref(null);

const typeLabel = {
    video: 'Video',
    embed: 'Video',
    document: 'Archivo',
};

/** ¿Se puede reproducir dentro del portal? */
function playable(tutorial) {
    return tutorial.media_type === 'video' || tutorial.media_type === 'embed';
}

function open(tutorial) {
    // Los documentos (PDF, Word, imágenes…) se abren en otra pestaña.
    if (!playable(tutorial)) {
        window.open(tutorial.src, '_blank', 'noopener');

        return;
    }

    active.value = tutorial;
}

function closeDialog() {
    active.value = null;
}
</script>

<template>
    <Head title="Tutoriales" />

    <AppLayout title="Tutoriales">
        <div class="tut-header">
            <div>
                <h2 class="tut-title">Videos y guías de uso</h2>
                <p class="tut-subtitle">
                    Aprende a usar el portal: cómo consultar tus servicios, registrar un pago y descargar tu estado de cuenta.
                </p>
            </div>
        </div>

        <el-row v-if="tutorials.length" :gutter="16">
            <el-col v-for="tutorial in tutorials" :key="tutorial.id" :xs="24" :sm="12" :lg="8" class="tut-col">
                <el-card shadow="hover" class="tut-card" @click="open(tutorial)">
                    <div class="tut-thumb" :class="{ 'tut-thumb--document': !playable(tutorial) }">
                        <img v-if="tutorial.poster" :src="tutorial.poster" :alt="tutorial.title" class="tut-poster" />
                        <el-icon v-else :size="42" class="tut-thumb-icon">
                            <VideoPlay v-if="playable(tutorial)" />
                            <Document v-else />
                        </el-icon>

                        <span v-if="playable(tutorial)" class="tut-play">
                            <el-icon :size="26"><VideoPlay /></el-icon>
                        </span>
                    </div>

                    <div class="tut-body">
                        <div class="tut-card-title">{{ tutorial.title }}</div>
                        <p class="tut-card-description">{{ tutorial.description || 'Sin descripción.' }}</p>

                        <div class="tut-meta">
                            <el-tag size="small" effect="plain" type="primary">
                                {{ typeLabel[tutorial.media_type] || 'Archivo' }}
                            </el-tag>
                            <span v-if="tutorial.size_human" class="tut-size">{{ tutorial.size_human }}</span>
                        </div>

                        <el-button class="tut-action" type="primary" plain size="small" @click.stop="open(tutorial)">
                            <el-icon class="tut-action-icon">
                                <component :is="playable(tutorial) ? 'VideoPlay' : 'View'" />
                            </el-icon>
                            {{ playable(tutorial) ? 'Ver tutorial' : 'Abrir archivo' }}
                        </el-button>
                    </div>
                </el-card>
            </el-col>
        </el-row>

        <el-empty
            v-else
            description="Todavía no hay tutoriales publicados. Vuelve a revisar pronto."
        />

        <!-- El reproductor solo se monta al elegir un tutorial (nada se descarga antes). -->
        <TutorialDialog v-if="active" :tutorial="active" @close="closeDialog" />
    </AppLayout>
</template>

<style scoped>
.tut-header {
    margin-bottom: 20px;
}

.tut-title {
    margin: 0 0 4px;
    font-size: 20px;
    font-weight: 700;
    color: #1e3a8a;
}

.tut-subtitle {
    margin: 0;
    font-size: 13px;
    color: #64748b;
}

.tut-col {
    margin-bottom: 16px;
}

.tut-card {
    cursor: pointer;
}

.tut-thumb {
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    height: 158px;
    margin: -20px -20px 14px;
    background: linear-gradient(135deg, #1e3a8a 0%, #2b52b0 100%);
    border-radius: 4px 4px 0 0;
    overflow: hidden;
}

.tut-thumb--document {
    background: linear-gradient(135deg, #facc15 0%, #f59e0b 100%);
}

.tut-poster {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.tut-thumb-icon {
    color: rgba(255, 255, 255, 0.92);
}

.tut-thumb--document .tut-thumb-icon {
    color: #1e3a8a;
}

.tut-play {
    position: absolute;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 54px;
    height: 54px;
    color: #1e3a8a;
    background: rgba(255, 255, 255, 0.92);
    border-radius: 50%;
    box-shadow: 0 6px 18px -6px rgba(15, 23, 42, 0.6);
}

.tut-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.tut-card-title {
    font-size: 15px;
    font-weight: 700;
    color: #1e3a8a;
}

.tut-card-description {
    margin: 0;
    min-height: 36px;
    font-size: 13px;
    color: #64748b;
}

.tut-meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 12px;
    color: #94a3b8;
}

.tut-action {
    align-self: flex-start;
}

.tut-action-icon {
    margin-right: 4px;
}
</style>
