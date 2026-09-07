import './bootstrap';
import '../css/app.css';

import ElementPlus from 'element-plus';
import 'element-plus/dist/index.css';
import * as ElementPlusIconsVue from '@element-plus/icons-vue';
import es from 'element-plus/es/locale/lang/es';
import dayjs from 'dayjs';
import 'dayjs/locale/es';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

dayjs.locale('es');

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./Pages/${name}.vue`, import.meta.glob('./Pages/**/*.vue')),
    setup({ el, App, props, plugin }) {
        const app = createApp({ render: () => h(App, props) });

        // Registro global de todos los iconos de Element Plus
        for (const [key, component] of Object.entries(ElementPlusIconsVue)) {
            app.component(key, component);
        }

        return app
            .use(plugin)
            .use(ElementPlus, { locale: es })
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#10b981',
    },
});
