import DOMPurify from 'dompurify';

/**
 * Sanitize an untrusted HTML string before assigning it to `innerHTML`.
 *
 * Document previews (e.g. an xlsx sheet rendered to an HTML table) contain
 * user-controlled cell content, so the generated markup must never be trusted.
 * DOMPurify strips scripts, inline event handlers, and `javascript:` URLs while
 * leaving benign formatting/table markup intact.
 */
export function sanitizeHtml(dirty: string): string {
    return DOMPurify.sanitize(dirty, { USE_PROFILES: { html: true } });
}
