/**
 * Trigger a browser download without a full-page navigation.
 *
 * Using `window.location.href` for a file download tears down the SPA — it
 * loses scroll position, selection, and any in-page search/filter state. A
 * transient anchor with the `download` attribute fetches the attachment in the
 * background and leaves the Vue app untouched.
 */
export function triggerDownload(url: string): void {
    const a = document.createElement('a');
    a.href = url;
    a.download = '';
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
}
