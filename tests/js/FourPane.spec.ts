import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FourPane from '@/Components/FourPane.vue';

const slots = {
    rail: '<div class="s-rail">RAIL</div>',
    folders: '<div class="s-folders">FOLDERS</div>',
    contents: '<div class="s-contents">CONTENTS</div>',
    detail: '<div class="s-detail">DETAIL</div>',
};

function paneEl(wrapper: ReturnType<typeof mount>, name: string) {
    return wrapper.get(`[data-pane="${name}"]`);
}

describe('FourPane', () => {
    it('renders all four named slots', () => {
        const wrapper = mount(FourPane, { slots });
        expect(wrapper.find('.s-rail').exists()).toBe(true);
        expect(wrapper.find('.s-folders').exists()).toBe(true);
        expect(wrapper.find('.s-contents').exists()).toBe(true);
        expect(wrapper.find('.s-detail').exists()).toBe(true);
    });

    it('hides the non-active panes (single-pane mobile behavior)', () => {
        const wrapper = mount(FourPane, { slots, props: { activePane: 'folders' } });
        expect(paneEl(wrapper, 'rail').classes()).toContain('fp-pane--hidden');
        expect(paneEl(wrapper, 'folders').classes()).not.toContain('fp-pane--hidden');
        expect(paneEl(wrapper, 'contents').classes()).toContain('fp-pane--hidden');
        expect(paneEl(wrapper, 'detail').classes()).toContain('fp-pane--hidden');
    });

    it('walks back detail -> contents -> folders -> rail via the back control', async () => {
        const wrapper = mount(FourPane, {
            slots,
            props: { activePane: 'detail', 'onUpdate:activePane': (v: string) => wrapper.setProps({ activePane: v }) },
        });
        const back = () => wrapper.get('[data-testid="fp-back"]').trigger('click');
        await back();
        expect(wrapper.props('activePane')).toBe('contents');
        await back();
        expect(wrapper.props('activePane')).toBe('folders');
        await back();
        expect(wrapper.props('activePane')).toBe('rail');
    });

    it('hides the back control on the first (rail) pane', () => {
        const wrapper = mount(FourPane, { slots, props: { activePane: 'rail' } });
        expect(wrapper.find('[data-testid="fp-back"]').exists()).toBe(false);
    });
});
