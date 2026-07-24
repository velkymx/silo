import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const s = vi.hoisted(() => ({
    post: vi.fn(),
    del: vi.fn(),
    get: vi.fn(() => Promise.resolve({ posts: [], hasMore: false })),
    confirm: vi.fn(() => Promise.resolve(true)),
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { post: s.post, delete: s.del, get: vi.fn() },
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
vi.mock('@/lib/http', () => ({ http: { get: s.get } }));
vi.mock('@/composables/useConfirm', () => ({
    useConfirm: () => ({ confirm: s.confirm }),
    usePrompt: () => ({ prompt: vi.fn() }),
}));

import Wall from '@/Components/Wall/Wall.vue';
import WallPostCard, { type WallPostShape } from '@/Components/Wall/WallPostCard.vue';
import WallComposer from '@/Components/Wall/WallComposer.vue';
import WallReactions from '@/Components/Wall/WallReactions.vue';

const post: WallPostShape = {
    id: 7,
    body: '<p>Hey <strong>you</strong></p>',
    created_at: new Date().toISOString(),
    author: { id: 3, name: 'Tom', avatar_url: null },
    can_delete: false,
    reactions: [{ icon: 'fire', count: 2, mine: true }],
};

beforeEach(() => Object.values(s).forEach((f) => f.mockClear()));

describe('WallPostCard', () => {
    it('renders the author, sanitized body, and no delete for bystanders', () => {
        const wrapper = mount(WallPostCard, { props: { post } });
        expect(wrapper.text()).toContain('Tom');
        expect(wrapper.find('.wall-post__body').html()).toContain('<strong>you</strong>');
        expect(wrapper.find('[data-testid="wall-delete"]').exists()).toBe(false);
        // Author name links to their profile.
        expect(wrapper.find('a[href="/directory/3"]').exists()).toBe(true);
    });

    it('strips scripts client-side even if one sneaks in', () => {
        const wrapper = mount(WallPostCard, {
            props: { post: { ...post, body: '<p>ok</p><script>alert(1)</script>' } },
        });
        expect(wrapper.find('.wall-post__body').html()).not.toContain('<script');
    });

    it('upsizes a post into a note', async () => {
        const wrapper = mount(WallPostCard, { props: { post } });
        await wrapper.get('[data-testid="wall-upsize"]').trigger('click');
        expect(s.post).toHaveBeenCalledWith('/wall/7/upsize');
    });

    it('deletes after confirm when allowed', async () => {
        const wrapper = mount(WallPostCard, { props: { post: { ...post, can_delete: true } } });
        await wrapper.get('[data-testid="wall-delete"]').trigger('click');
        await flushPromises();
        expect(s.confirm).toHaveBeenCalled();
        expect(s.del).toHaveBeenCalledWith('/wall/7', expect.anything());
    });
});

describe('WallReactions', () => {
    it('shows chips with counts and toggles on click', async () => {
        const wrapper = mount(WallReactions, { props: { postId: 7, reactions: post.reactions } });
        const chip = wrapper.get('[data-reaction="fire"]');
        expect(chip.text()).toContain('2');
        expect(chip.classes()).toContain('wall-chip--mine');
        await chip.trigger('click');
        expect(s.post).toHaveBeenCalledWith('/wall/7/react', { icon: 'fire' }, expect.anything());
    });

    it('picker reacts with a chosen icon', async () => {
        const wrapper = mount(WallReactions, { props: { postId: 7, reactions: [] } });
        await wrapper.get('[data-pick="rocket-takeoff"]').trigger('click');
        expect(s.post).toHaveBeenCalledWith('/wall/7/react', { icon: 'rocket-takeoff' }, expect.anything());
    });
});

describe('WallComposer', () => {
    it('disables Post while empty and posts the body to the right wall', async () => {
        const wrapper = mount(WallComposer, { props: { wallUserId: 9 } });
        const btn = wrapper.get('[data-testid="wall-post-btn"]');
        expect(btn.attributes('disabled')).toBeDefined();

        await wrapper.find('input[data-stub="VibeFormWysiwyg"]').setValue('<p>hello</p>');
        await btn.trigger('click');
        expect(s.post).toHaveBeenCalledWith('/wall', { body: '<p>hello</p>', wall_user_id: 9 }, expect.anything());
    });
});

describe('Wall', () => {
    it('renders composer, posts, and an empty state when bare', () => {
        const empty = mount(Wall, { props: { posts: [] } });
        expect(empty.find('[data-testid="wall-empty"]').exists()).toBe(true);

        const filled = mount(Wall, { props: { posts: [post] } });
        expect(filled.findComponent(WallPostCard).exists()).toBe(true);
        expect(filled.findComponent(WallComposer).exists()).toBe(true);
        expect(filled.find('[data-testid="wall-empty"]').exists()).toBe(false);
    });
});
