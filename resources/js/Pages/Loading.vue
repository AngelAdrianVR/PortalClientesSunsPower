<script setup>
import { onMounted, ref } from 'vue';
import { router, Head } from '@inertiajs/vue3';

// `authenticated` lo envía la ruta raíz: true si el cliente ya tiene sesión.
const props = defineProps({
    authenticated: { type: Boolean, default: false },
});

// Tiempo total antes de redirigir (en milisegundos)
const TOTAL_DURATION = 3500;
const showText = ref(false);

onMounted(() => {
    // Activar la animación del texto poco después de que inicie el sol
    setTimeout(() => {
        showText.value = true;
    }, 1200);

    // Redirigir al login (o al dashboard si ya hay sesión) tras la animación
    setTimeout(() => {
        router.visit(props.authenticated ? '/dashboard' : '/login');
    }, TOTAL_DURATION);
});
</script>

<template>
<Head title="Bienvenido" />
    <!-- Fondo: Gradiente sutil y luminoso que evoca el cielo matutino -->
    <div class="fixed inset-0 z-50 flex flex-col items-center justify-center min-h-screen overflow-hidden bg-gradient-to-tr from-blue-50 via-white to-yellow-50">
        
        <!-- Efectos de fondo (Destellos solares sutiles) -->
        <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[600px] h-[600px] bg-yellow-200/20 rounded-full blur-3xl animate-pulse-slow pointer-events-none"></div>
        <div class="absolute bottom-0 right-0 w-[400px] h-[400px] bg-blue-100/30 rounded-full blur-3xl pointer-events-none"></div>

        <div class="relative flex flex-col items-center z-10 p-10 space-y-8">
            
            <!-- Contenedor del Isotipo Solar Animado -->
            <div class="relative w-32 h-32 flex items-center justify-center sun-container-enter">
                <!-- Anillo Orbital Externo Giratorio -->
                <div class="absolute inset-0 rounded-full border-2 border-transparent border-t-yellow-400 border-r-yellow-300 shadow-[0_0_15px_rgba(250,204,21,0.3)] animate-spin-slow ring-glow"></div>
                
                <!-- Anillo Interno Pulsante -->
                <div class="absolute inset-4 bg-gradient-to-tr from-yellow-300 to-orange-400 rounded-full blur-[2px] opacity-70 animate-pulse-core"></div>
                
                <!-- El "Sol" Central (Reemplaza la imagen con tu SVG o PNG de sol si tienes uno) -->
                <!-- Usamos un div temporal que simula un sol moderno -->
                <div class="relative w-24 h-24 bg-gradient-to-br from-yellow-400 to-orange-500 rounded-full shadow-lg flex items-center justify-center overflow-hidden">
                     <!-- Si tienes un logo real, usa esto: -->
                     <!-- <img src="/images/sun-icon.png" alt="Sol" class="w-12 h-12 object-contain" /> -->
                     
                     <!-- Placeholder SVG de un sol estilizado -->
                    <img src="@/../../public/images/isologo-suns-power-mx.png" draggable="false" class="w-full h-full object-cover" onerror="this.src='https://ui-avatars.com/api/?name=Solar+ERP&background=0D8ABC&color=fff&rounded=true&font-size=0.4'" alt="Logo" />
                </div>
            </div>

            <!-- Texto de la Marca con Efecto de Revelado -->
            <div class="text-center overflow-hidden h-16 flex items-center">
                <h1 
                    class="text-3xl md:text-4xl font-extrabold tracking-tight text-transparent bg-clip-text bg-gradient-to-r from-blue-800 to-blue-600 transition-all duration-1000 transform"
                    :class="showText ? 'translate-y-0 opacity-100 filter blur-0' : 'translate-y-10 opacity-0 filter blur-sm'"
                >
                    SUN'S POWER <span class="text-yellow-500">MX</span>
                </h1>
            </div>

             <!-- Indicador de carga pequeño opcional -->
            <div class="mt-4 transition-opacity duration-700 delay-1000" :class="showText ? 'opacity-100' : 'opacity-0'">
                <div class="flex space-x-2">
                    <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce delay-75"></div>
                    <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce delay-150"></div>
                    <div class="w-2 h-2 bg-blue-600 rounded-full animate-bounce delay-300"></div>
                </div>
            </div>

        </div>
    </div>
</template>

<style scoped>
/* Animación de entrada del contenedor del sol */
.sun-container-enter {
    animation: scaleInElastic 1.2s cubic-bezier(0.34, 1.56, 0.64, 1) forwards;
    opacity: 0;
    transform: scale(0.5);
}

@keyframes scaleInElastic {
    0% {
        opacity: 0;
        transform: scale(0.5) rotate(-10deg);
    }
    60% {
        opacity: 1;
        transform: scale(1.05) rotate(2deg);
    }
    100% {
        opacity: 1;
        transform: scale(1) rotate(0deg);
    }
}

/* Rotación lenta para el anillo orbital */
.animate-spin-slow {
    animation: spin 4s linear infinite;
}

/* Pulso del núcleo solar */
.animate-pulse-core {
    animation: pulseCore 2.5s ease-in-out infinite;
}

@keyframes pulseCore {
    0%, 100% { transform: scale(0.95); opacity: 0.6; }
    50% { transform: scale(1.15); opacity: 0.9; }
}

/* Pulso lento para el fondo atmosférico */
.animate-pulse-slow {
    animation: pulse 6s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Brillo extra en el anillo */
.ring-glow {
    filter: drop-shadow(0 0 8px rgba(234, 179, 8, 0.5));
}
</style>