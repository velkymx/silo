import { mount } from '@vue/test-utils';
import { nextTick } from 'vue';
import { describe, it, expect, beforeEach } from 'vitest';
import Sodoku from '@/Pages/Break/Sodoku.vue';

// Standard valid 9x9 solution; (0,0) is a 1.
const solution = Array.from({ length: 9 }, (_, r) =>
    Array.from({ length: 9 }, (_, c) => String(((r * 3 + Math.floor(r / 3) + c) % 9) + 1)).join(''),
).join('');
const puzzle = '-'.repeat(81); // no givens, every cell is editable

const props = {
    puzzle,
    solution,
    difficulty: 'beginner' as const,
    date: '2026-11-08',
    seed: 1,
    difficultyOptions: [
        { value: 'beginner' as const, text: 'Beginner', sub: '' },
        { value: 'intermediate' as const, text: 'Intermediate', sub: '' },
        { value: 'advanced' as const, text: 'Advanced', sub: '' },
    ],
};

function mountGame() {
    localStorage.clear();
    return mount(Sodoku, {
        props,
        global: {
            stubs: {
                BreakGameShell: {
                    template: '<div><slot name="subtitle" /><slot name="actions" /><slot /><slot name="message" /><slot name="extra" /></div>',
                },
            },
        },
    });
}

function press(key: string): void {
    window.dispatchEvent(new KeyboardEvent('keydown', { key, bubbles: true }));
}

async function clickCheck(wrapper: ReturnType<typeof mountGame>): Promise<void> {
    const button = wrapper.findAll('button').find((b) => b.text().includes('Check'));
    if (!button) throw new Error('Check button not found');
    await button.trigger('click');
}

describe('Sodoku mistake tracking', () => {
    beforeEach(() => localStorage.clear());

    it('records three separate wrong entries and loses at the limit', async () => {
        const wrapper = mountGame();

        // Select (0,0) and enter a wrong 2, then Check.
        press('ArrowRight');
        press('2');
        await clickCheck(wrapper);
        expect(wrapper.text()).toContain('1 / 3');

        // Fix/erase and enter another wrong digit -> second mistake.
        press('ArrowLeft'); // advanceAfterMove moved selection to (0,1)
        press('0');
        press('3');
        await clickCheck(wrapper);
        expect(wrapper.text()).toContain('2 / 3');

        // A third separate mistake reaches MAX_MISTAKES.
        press('ArrowLeft');
        press('0');
        press('4');
        await clickCheck(wrapper);
        expect(wrapper.text()).toContain('3 / 3');
        expect(wrapper.text()).toContain('Three mistakes');

        wrapper.unmount();
    });

    it('reaches the limit from wrong entries alone, without pressing Check', async () => {
        const wrapper = mountGame();

        press('ArrowRight');
        press('2'); // first wrong entry
        await nextTick();
        expect(wrapper.text()).toContain('1 / 3');

        press('ArrowLeft');
        press('0');
        press('3'); // second
        press('ArrowLeft');
        press('0');
        press('4'); // third -> game over
        await nextTick();

        expect(wrapper.text()).toContain('3 / 3');
        expect(wrapper.text()).toContain('Three mistakes');

        wrapper.unmount();
    });

    it('does not double-charge when Check is pressed twice on an unchanged board', async () => {
        const wrapper = mountGame();

        press('ArrowRight');
        press('2');
        await clickCheck(wrapper);
        await clickCheck(wrapper);
        await clickCheck(wrapper);

        expect(wrapper.text()).toContain('1 / 3');

        wrapper.unmount();
    });
});
