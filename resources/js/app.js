import 'bootstrap/dist/css/bootstrap.min.css';
import 'bootstrap-icons/font/bootstrap-icons.css';
import 'material-icons/iconfont/material-icons.css'; // jspreadsheet toolbar glyphs (bundled, no CDN)
import '../css/theme.css';
import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import VibeUI, { useColorMode } from '@velkymx/vibeui';

// Restore the saved theme and follow the OS in "auto" mode.
useColorMode().initColorMode();

createInertiaApp({
    resolve: (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(VibeUI)
            .mount(el);
    },
});
