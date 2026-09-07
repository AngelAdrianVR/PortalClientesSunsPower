<script setup>
import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Navegación lateral del portal (escritorio + móvil).
 *
 * IMPORTANTE: el menú NO usa la prop `router` de Element Plus (requiere
 * vue-router). La navegación se hace con Inertia mediante @select.
 */
const props = defineProps({
    isMobile: { type: Boolean, default: false },
    drawerVisible: { type: Boolean, default: false },
});

const emit = defineEmits(['update:drawerVisible']);

const page = usePage();
const collapsed = ref(false);

const drawer = computed({
    get: () => props.drawerVisible,
    set: (value) => emit('update:drawerVisible', value),
});

const activeIndex = computed(() => {
    const url = page.url || '';

    if (url.startsWith('/servicios')) {
        return '/servicios';
    }

    return '/dashboard';
});

function onSelect(index) {
    router.visit(index);
    drawer.value = false;
}

function goDashboard() {
    onSelect('/dashboard');
}
</script>

<template>
    <!-- Sidenav de escritorio -->
    <el-aside v-if="!isMobile" class="portal-aside" :width="collapsed ? '76px' : '252px'">
        <div class="portal-brand" @click="goDashboard">
            <img
                src="/images/isologo-suns-power-mx.png"
                alt="SUN'S POWER MX"
                class="portal-logo"
                :class="{ 'portal-logo--collapsed': collapsed }"
                onerror="this.style.display='none'"
            />
            <span v-if="!collapsed" class="portal-brand-name">SUN'S POWER MX</span>
        </div>

        <el-menu
            :default-active="activeIndex"
            :collapse="collapsed"
            :collapse-transition="false"
            class="portal-menu"
            @select="onSelect"
        >
            <el-menu-item index="/dashboard">
                <el-icon><Odometer /></el-icon>
                <template #title>Dashboard</template>
            </el-menu-item>
            <el-menu-item index="/servicios">
                <el-icon><Grid /></el-icon>
                <template #title>Servicios</template>
            </el-menu-item>
        </el-menu>

        <div class="portal-aside-footer">
            <el-button text class="collapse-btn" @click="collapsed = !collapsed">
                <el-icon :size="18">
                    <component :is="collapsed ? 'Expand' : 'Fold'" />
                </el-icon>
            </el-button>
        </div>
    </el-aside>

    <!-- Sidenav móvil -->
    <el-drawer v-model="drawer" direction="ltr" size="264px" :with-header="false">
        <div class="portal-brand portal-brand--drawer" @click="goDashboard">
            <img
                src="/images/isologo-suns-power-mx.png"
                alt="SUN'S POWER MX"
                class="portal-logo"
                onerror="this.style.display='none'"
            />
            <span class="portal-brand-name">SUN'S POWER MX</span>
        </div>

        <el-menu :default-active="activeIndex" class="portal-menu" @select="onSelect">
            <el-menu-item index="/dashboard">
                <el-icon><Odometer /></el-icon>
                <template #title>Dashboard</template>
            </el-menu-item>
            <el-menu-item index="/servicios">
                <el-icon><Grid /></el-icon>
                <template #title>Servicios</template>
            </el-menu-item>
        </el-menu>
    </el-drawer>
</template>

<style scoped>
.portal-aside {
    display: flex;
    flex-direction: column;
    background: #ffffff;
    border-right: 1px solid #e4e7ed;
    transition: width 0.2s ease;
}

.portal-brand {
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    padding: 22px 10px 18px;
    cursor: pointer;
    border-bottom: 1px solid #f0f2f5;
    min-height: 122px;
}

.portal-brand--drawer {
    min-height: auto;
    padding: 20px 12px;
}

.portal-logo {
    width: 66px;
    height: 66px;
    object-fit: contain;
    border-radius: 16px;
    background: #ffffff;
    border: 1px solid #e6e9ef;
    padding: 6px;
    box-shadow: 0 10px 20px -10px rgba(30, 58, 138, 0.45);
}

.portal-logo--collapsed {
    width: 46px;
    height: 46px;
    border-radius: 12px;
    padding: 4px;
}

.portal-brand-name {
    font-weight: 800;
    color: #1e3a8a;
    font-size: 13px;
    letter-spacing: 0.12em;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
}

.portal-menu {
    border-right: none;
    flex: 1;
    padding: 12px 8px;
    --el-menu-active-color: #1e3a8a;
    --el-menu-hover-bg-color: #fffbeb;
    --el-menu-bg-color: transparent;
}

.portal-menu :deep(.el-menu-item) {
    height: 46px;
    line-height: 46px;
    border-radius: 10px;
    margin-bottom: 4px;
    color: #475569;
}

.portal-menu :deep(.el-menu-item:hover) {
    background-color: #fffbeb;
    color: #1e3a8a;
}

.portal-menu :deep(.el-menu-item.is-active) {
    background-color: #eef2ff;
    color: #1e3a8a;
    font-weight: 700;
    box-shadow: inset 3px 0 0 0 #facc15;
}

.portal-aside-footer {
    padding: 10px;
    border-top: 1px solid #f0f2f5;
}

.collapse-btn {
    width: 100%;
    color: #1e3a8a;
}
</style>
