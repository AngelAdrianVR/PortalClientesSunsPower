<script setup>
import { ElCard } from 'element-plus';
</script>

<template>
    <div class="auth-bg relative min-h-screen flex overflow-hidden">
        <!-- Blobs decorativos de fondo adaptados a los colores corporativos -->
        <div class="blob blob-one"></div>
        <div class="blob blob-two"></div>
        <div class="blob blob-three"></div>

        <div class="relative z-10 m-auto w-full max-w-md px-4 py-8 animate-fade-in">
            <div class="flex justify-center mb-8">
                <slot name="logo" />
            </div>

            <!-- Contenedor relativo para la barra decorativa superior -->
            <div class="relative">
                <div class="absolute top-0 left-0 w-full h-1 bg-gradient-to-r from-blue-900 via-yellow-400 to-blue-900 rounded-t-lg z-20"></div>
                
                <el-card class="custom-auth-card relative overflow-hidden" :body-style="{ padding: '32px' }" shadow="always">
                    <!-- Decoración de fondo sutil (efecto de luz dorada) -->
                    <div class="absolute top-0 right-0 -mt-4 -mr-4 w-24 h-24 bg-yellow-400 opacity-10 rounded-full blur-xl pointer-events-none"></div>
                    
                    <slot />
                </el-card>
            </div>

            <p class="text-center mt-6 text-xs text-gray-500 select-none">
                Portal de Clientes · Sun's Power MX
            </p>
        </div>
    </div>
</template>

<style>
/* Sobrescritura global de variables para Element Plus para lograr el "Sun's Style" */
:root {
    --el-color-primary: #0F3460;
    --el-color-primary-light-3: #16213E;
    --el-border-radius-base: 6px;
    
    /* Efecto de luz amarillo para inputs */
    --el-input-focus-border-color: #FFD700;
    --el-input-hover-border-color: #FFD700;
    
    /* Botones primarios */
    --el-button-font-weight: 600;

    /* Checkbox */
    --el-checkbox-checked-bg-color: #0F3460;
    --el-checkbox-checked-input-border-color: #0F3460;
}

/* Ajustes específicos para la tarjeta imitando el Card 1 */
.custom-auth-card {
    border-radius: 12px !important;
    border: 1px solid rgba(255, 193, 7, 0.3) !important;
    box-shadow: 0 8px 16px -4px rgba(15, 52, 96, 0.15) !important;
    background-color: #ffffff;
}

/* Efecto de halo de luz dorada en los inputs de Element Plus */
.el-input__wrapper.is-focus {
    box-shadow: 0 0 0 1px #FFD700 inset, 0 0 0 2px rgba(255, 215, 0, 0.2) !important;
}
</style>

<style scoped>
.auth-bg {
    /* Fondo azul muy claro/grisáceo para contrastar */
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
}

.blob {
    position: absolute;
    border-radius: 9999px;
    filter: blur(80px);
    pointer-events: none;
}

/* Azul corporativo tenue */
.blob-one {
    width: 360px;
    height: 360px;
    background: #0F3460;
    opacity: 0.08;
    top: -120px;
    left: -120px;
}

/* Amarillo/Dorado tenue */
.blob-two {
    width: 320px;
    height: 320px;
    background: #FFD700;
    opacity: 0.12;
    bottom: -110px;
    right: -100px;
}

/* Azul un poco más vibrante tenue */
.blob-three {
    width: 220px;
    height: 220px;
    background: #16213E;
    opacity: 0.05;
    top: 46%;
    left: 56%;
}

.animate-fade-in {
    animation: fadeIn 0.45s ease-out;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(12px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>