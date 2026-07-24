/**
 * Relative-time formatting shared by the RSS surfaces (item rows, stats panel).
 *
 * `short` is compact for dense lists ("3h", then the date past a week);
 * `ago` reads as a sentence fragment ("3h ago", "never" when absent).
 */
export function useRelativeTime() {
    function diffSeconds(iso: string): number {
        return Math.floor((Date.now() - new Date(iso).getTime()) / 1000);
    }

    function short(iso: string | null): string {
        if (!iso) return '';
        const diff = diffSeconds(iso);
        if (diff < 60) return 'just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
        if (diff < 604800) return `${Math.floor(diff / 86400)}d`;
        return new Date(iso).toLocaleDateString();
    }

    function ago(iso: string | null): string {
        if (!iso) return 'never';
        const diff = diffSeconds(iso);
        if (diff < 60) return 'just now';
        if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
        if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
        return `${Math.floor(diff / 86400)}d ago`;
    }

    return { short, ago };
}
