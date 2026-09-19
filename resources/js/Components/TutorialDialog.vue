<script setup>
import { computed } from 'vue';

/**
 * Reproductor del tutorial en un diálogo.
 *
 * IMPORTANTE (rendimiento): el componente se monta solo al elegir un tutorial
 * y el <video> usa `preload="metadata"`. Con el archivo servido como estático
 * (con soporte de rangos) el navegador descarga únicamente la cabecera y luego
 * lo que se va reproduciendo, nunca el archivo completo de golpe.
 */
const props = defineProps({
    tutorial: { type: Object, required: true },
});

const emit = defineEmits(['close']);

const visible = computed({
    get: () => true,
    set: (value) => {
        if (!value) {
            emit('close');
        }
    },
});
</script>

<template>
    <el-dialog
        v-model="visible"
        :title="tutorial.title"
        width="min(1000px, 95vw)"
        top="6vh"
        destroy-on-close
        align-center
        class="tut-dialog"
    >
        <!-- Video propio (o enlace directo a un .mp4/.webm) -->
        <video
            v-if="tutorial.media_type === 'video'"
            class="tut-video"
            :src="tutorial.src"
            :poster="tutorial.poster || undefined"
            controls
            playsinline
            preload="metadata"
        ></video>

        <!-- Video alojado en YouTube/Vimeo: se carga al abrir el diálogo -->
        <div v-else-if="tutorial.embed" class="tut-embed">
            <iframe
                :src="tutorial.embed"
                :title="tutorial.title"
                allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen
            ></iframe>
        </div>

        <p v-if="tutorial.description" class="tut-description">{{ tutorial.description }}</p>
    </el-dialog>
</template>

<style scoped>
.tut-video,
.tut-embed {
    width: 100%;
    aspect-ratio: 16 / 9;
    background: #000;
    border-radius: 10px;
    overflow: hidden;
}

.tut-video {
    display: block;
}

.tut-embed iframe {
    width: 100%;
    height: 100%;
    border: 0;
}

.tut-description {
    margin: 12px 0 0;
    font-size: 13px;
    color: #475569;
}
</style>
