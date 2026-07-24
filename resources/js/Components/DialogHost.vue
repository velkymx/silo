<script setup lang="ts">
import { useDialogHost } from '../composables/useConfirm';

// Single in-app confirm/prompt host (replaces native window.confirm/prompt).
// Mounted once per layout; every component's confirm()/prompt() call drives
// the same module-level dialog state.
const { state: dialog, accept: dialogAccept, cancel: dialogCancel } = useDialogHost();
</script>

<template>
    <VibeModal v-model="dialog.open" :title="dialog.title" size="sm" centered @hide="dialogCancel">
        <p class="mb-0 dialog-host-msg">{{ dialog.message }}</p>
        <VibeFormInput
            v-if="dialog.mode === 'prompt'"
            v-model="dialog.inputValue"
            :placeholder="dialog.placeholder"
            class="mt-3"
            autofocus
            @keyup.enter="dialogAccept"
        />
        <template #footer>
            <VibeButton variant="secondary" outline @click="dialogCancel">{{ dialog.cancelLabel }}</VibeButton>
            <VibeButton :variant="dialog.variant" @click="dialogAccept">{{ dialog.confirmLabel }}</VibeButton>
        </template>
    </VibeModal>
</template>

<style>
/* confirm()/prompt() can fire while another modal is open (Save as Smart
   Folder from Advanced Search, purge confirms from viewers). Bootstrap
   stacks every modal at the same z-index, so without this the dialog
   paints UNDER the caller's modal and can't be clicked. VibeModal
   teleports and drops fallthrough attrs, so key on our message class. */
.modal:has(.dialog-host-msg) {
    z-index: 1080;
}
</style>
