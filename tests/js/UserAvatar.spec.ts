import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';

import UserAvatar from '@/Components/UserAvatar.vue';

const withAvatar = { name: 'Alice', avatar_url: 'https://example.com/a.jpg' };
const withoutAvatar = { name: 'Bob Lang', avatar_url: null };

describe('UserAvatar', () => {
    it('renders an img when avatar_url is set', () => {
        const wrapper = mount(UserAvatar, { props: { user: withAvatar } });
        const img = wrapper.find('img.rounded-circle');
        expect(img.exists()).toBe(true);
        expect(img.attributes('src')).toBe('https://example.com/a.jpg');
    });

    it('renders initials when avatar_url is null', () => {
        const wrapper = mount(UserAvatar, { props: { user: withoutAvatar } });
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.text()).toContain('BL');
    });

    it('applies the size prop to width/height', () => {
        const wrapper = mount(UserAvatar, { props: { user: withAvatar, size: 48 } });
        const img = wrapper.find('img');
        expect(img.attributes('style')).toContain('48px');
    });

    it('scales font size proportionally for initials fallback', () => {
        const wrapper = mount(UserAvatar, { props: { user: withoutAvatar, size: 72 } });
        const span = wrapper.find('span.rounded-circle');
        // 72 * 0.42 ≈ 30, but clamped to at least 10.
        expect(span.attributes('style')).toMatch(/font-size:\s*30px/);
    });
});
