/**
 * True when a keyboard event originated inside a text-entry control
 * (text inputs, textareas, selects, contenteditable). Checkboxes, radios,
 * and buttons are form fields but NOT text entry — global shortcuts like
 * Escape must still work while one of those has focus.
 */
export function isTextInputTarget(e: KeyboardEvent): boolean {
    const target = e.target as HTMLElement | null;
    const tag = (target?.tagName || '').toLowerCase();
    const type = ((target as HTMLInputElement | null)?.type || '').toLowerCase();
    return (['input', 'textarea', 'select'].includes(tag) && !['checkbox', 'radio', 'button'].includes(type))
        || !!target?.isContentEditable;
}
