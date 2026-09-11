<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import AuthenticationCard from '@/Components/AuthenticationCard.vue';
import AuthenticationCardLogo from '@/Components/AuthenticationCardLogo.vue';

defineProps({
    status: String,
});

const form = useForm({
    // Identificador: correo, RFC, nombre completo o teléfono del cliente.
    email: '',
});

const submit = () => {
    form.post(route('login'));
};
</script>

<template>
    <Head title="Iniciar sesión" />

    <AuthenticationCard>
       <template #logo>
            <AuthenticationCardLogo class="w-20 h-20" />
        </template>

        <div class="text-center mb-7">
            <h2 class="text-2xl font-bold text-gray-800">Portal de Clientes</h2>
            <p class="text-sm text-gray-500 mt-1 leading-relaxed">
                Ingresa con el correo, RFC, nombre completo o teléfono con los que fuiste registrado como cliente.
            </p>
        </div>

        <el-alert v-if="status" type="success" :closable="false" show-icon class="mb-5">
            {{ status }}
        </el-alert>

        <form @submit.prevent="submit">
            <label class="block text-sm font-medium text-gray-700 mb-1.5" for="email">
                Correo, RFC, nombre completo o teléfono
            </label>

            <el-input
                id="email"
                v-model="form.email"
                size="large"
                placeholder="ej. Juan Robles, RFC o 5512345678"
                autofocus
                autocomplete="username"
                class="w-full"
            >
                <template #prefix>
                    <el-icon><User /></el-icon>
                </template>
            </el-input>

            <p v-if="form.errors.email" class="mt-1.5 text-sm text-red-600">
                {{ form.errors.email }}
            </p>

            <el-button
                type="primary"
                size="large"
                native-type="submit"
                class="w-full mt-6 !text-base font-semibold"
                :loading="form.processing"
                :disabled="form.processing"
            >
                Entrar al portal
            </el-button>
        </form>

        <div class="mt-6 pt-5 border-t border-gray-100 text-center">
            <p class="text-xs text-gray-400 leading-relaxed">
                Acceso exclusivo para clientes registrados de Sun's Power MX.
                Si no puedes ingresar, contacta a la empresa.
            </p>
        </div>
    </AuthenticationCard>
</template>
