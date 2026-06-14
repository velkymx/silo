/// <reference types="vite/client" />

declare module '*.vue' {
    import type { DefineComponent } from 'vue';
    const component: DefineComponent<Record<string, unknown>, Record<string, unknown>, unknown>;
    export default component;
}

interface AxiosLike {
    get(url: string, config?: unknown): Promise<{ data: unknown }>;
    post(url: string, data?: unknown, config?: unknown): Promise<{ data: unknown }>;
    put(url: string, data?: unknown, config?: unknown): Promise<{ data: unknown }>;
    delete(url: string, config?: unknown): Promise<{ data: unknown }>;
}

interface Window {
    axios: AxiosLike;
}
