import { onMounted, onBeforeUnmount } from 'vue';
import { isTextInputTarget } from '../lib/dom';

/**
 * Shell-surface convention: Escape clears the current selection and
 * collapses the detail pane. Skips text-entry fields (Escape there means
 * "leave the field"), but fires from checkboxes/buttons.
 */
export function useEscapeToClear(clear: () => void): void {
    function onKey(e: KeyboardEvent): void {
        if (!isTextInputTarget(e) && e.key === 'Escape') clear();
    }
    onMounted(() => window.addEventListener('keydown', onKey));
    onBeforeUnmount(() => window.removeEventListener('keydown', onKey));
}
