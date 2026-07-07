/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
    export default component;
}

// @velkymx/vibeui ships no bundled type declarations; declare its runtime
// surface loosely so consumers type-check. Components are globally registered.
declare module '@velkymx/vibeui' {
    import type { Ref } from 'vue';
    export function useColorMode(): {
        colorMode: Ref<'light' | 'dark'>;
        toggleColorMode: () => void;
    };
    const vibeui: unknown;
    export default vibeui;
}

