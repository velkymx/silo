import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { nextTick, defineComponent } from 'vue';
import AppModal from '../../resources/js/Components/AppModal.vue';
import AppFormGroup from '../../resources/js/Components/AppFormGroup.vue';

describe('AppModal', () => {
    it('focuses first focusable element when opened', async () => {
        const wrapper = mount(AppModal, {
            props: { modelValue: false },
            slots: { default: '<input data-testid="first" /><input data-testid="second" />' },
            attachTo: document.body,
        });
        await wrapper.setProps({ modelValue: true });
        await nextTick();
        expect(document.activeElement?.getAttribute('data-testid')).toBe('first');
        wrapper.unmount();
    });

    it('submits inner form on Cmd+Enter', async () => {
        const submitted = vi.fn();
        const SlotContent = defineComponent({
            setup() { return { submitted }; },
            template: '<form @submit.prevent="submitted"><input /><button type="submit">Go</button></form>',
        });
        const wrapper = mount(AppModal, {
            props: { modelValue: true },
            slots: { default: SlotContent },
        });
        await nextTick();
        await wrapper.trigger('keydown', { key: 'Enter', metaKey: true });
        expect(submitted).toHaveBeenCalled();
    });
});

describe('AppFormGroup', () => {
    it('wires aria-describedby to reference both helpText and error', async () => {
        const wrapper = mount(AppFormGroup, {
            props: { helpText: 'Hint text', error: 'Field required' },
            slots: { default: '<input />' },
        });
        await nextTick();
        const input = wrapper.find('input');
        const describedBy = input.attributes('aria-describedby') ?? '';
        expect(describedBy).toContain('-help');
        expect(describedBy).toContain('-error');
    });
});
