<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { ElMessage } from 'element-plus';
import { router, usePage } from '@inertiajs/vue3';
import SideNav from '@/Components/SideNav.vue';

defineProps({
    title: { type: String, default: 'Panel general' },
});

const page = usePage();
const portalClient = computed(() => page.props.portalClient);

watch(
    () => page.props.flash,
    (flash) => {
        if (flash?.success) {
            ElMessage.success(flash.success);
        }

        if (flash?.error) {
            ElMessage.error(flash.error);
        }
    },
    { immediate: true, deep: true }
);

const isMobile = ref(false);
const drawerVisible = ref(false);

function checkViewport() {
    isMobile.value = window.innerWidth < 992;
}

onMounted(() => {
    checkViewport();
    window.addEventListener('resize', checkViewport);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', checkViewport);
});

function logout() {
    router.post(route('logout'));
}

const initials = computed(() => {
    const name = portalClient.value?.name || 'C';

    return name
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((word) => word[0].toUpperCase())
        .join('');
});
</script>

<template>
    <el-container class="portal-shell">
        <!-- Navegación lateral (componente): logo, marca y menú Dashboard/Servicios -->
        <SideNav :is-mobile="isMobile" v-model:drawer-visible="drawerVisible" />

        <el-container class="portal-content">
            <el-header class="portal-header">
                <div class="portal-header-left">
                    <el-button v-if="isMobile" text class="menu-toggle" @click="drawerVisible = true">
                        <el-icon :size="20"><Menu /></el-icon>
                    </el-button>
                    <h1 class="portal-title">{{ title }}</h1>
                </div>

                <el-dropdown v-if="portalClient" trigger="click">
                    <div class="portal-user">
                        <el-avatar :size="34" class="portal-avatar">{{ initials }}</el-avatar>
                        <span class="portal-user-name">{{ portalClient.name }}</span>
                        <el-icon class="portal-user-caret"><ArrowDown /></el-icon>
                    </div>
                    <template #dropdown>
                        <el-dropdown-menu>
                            <el-dropdown-item disabled>
                                <span class="dropdown-client">Cliente: {{ portalClient.name }}</span>
                            </el-dropdown-item>
                            <el-dropdown-item divided @click="logout">
                                <el-icon><SwitchButton /></el-icon>
                                Cerrar sesión
                            </el-dropdown-item>
                        </el-dropdown-menu>
                    </template>
                </el-dropdown>
            </el-header>

            <el-main class="portal-main">
                <slot />
            </el-main>
        </el-container>
    </el-container>
</template>

<style scoped>
.portal-shell {
    height: 100vh;
    background: #f5f7fa;
}

.portal-content {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-width: 0;
}

.portal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    background: #ffffff;
    border-bottom: 1px solid #e4e7ed;
    height: 64px;
    padding: 0 24px;
}

.portal-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.portal-title {
    font-size: 18px;
    font-weight: 700;
    color: #1e3a8a;
    margin: 0;
}

.portal-user {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
}

.portal-avatar {
    background: #1e3a8a;
    color: #fff;
    font-weight: 600;
}

.portal-user-name {
    font-size: 14px;
    color: #334155;
    max-width: 180px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.portal-user-caret {
    color: #94a3b8;
}

.portal-main {
    overflow-y: auto;
    padding: 24px;
}

.dropdown-client {
    color: #64748b;
    font-size: 12px;
}
</style>
