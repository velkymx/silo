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
    // Lazy glob: each page is its own chunk, code-split on demand instead of
    // bundling every page into the initial payload.
    resolve: async (name) => {
        const pages = import.meta.glob('./Pages/**/*.vue');
        const path = `./Pages/${name}.vue`;
        const match = pages[path];

        if (!match) {
            throw new Error(`Inertia page component not found: ${path}`);
        }

        const page = await match();
        return page.default;
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(VibeUI)
            .mount(el);
    },
});
