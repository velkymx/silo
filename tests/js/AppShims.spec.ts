import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import AppFormGroup from '../../resources/js/Components/AppFormGroup.vue';

// VibeUI 1.1 owns auto-focus, Cmd+Enter, and aria-describedby natively.
// AppModal and AppFormGroup are now transparent shims; these smoke-tests verify
// the shim layer passes slot content and attrs through to VibeUI.

describe('AppFormGroup shim', () => {
    it('passes slot content through to VibeFormGroup', async () => {
        const wrapper = mount(AppFormGroup, {
            props: { label: 'Name', helpText: 'Hint', error: 'Required' },
            slots: { default: '<input />' },
        });
        await nextTick();
        expect(wrapper.find('input').exists()).toBe(true);
    });
});
