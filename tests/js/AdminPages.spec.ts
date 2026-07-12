import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const spies = vi.hoisted(() => ({
    formPost: vi.fn(),
    formPatch: vi.fn(),
    formDelete: vi.fn(),
    formReset: vi.fn(),
    formClearErrors: vi.fn(),
    routerGet: vi.fn(),
    routerVisit: vi.fn(),
    routerDelete: vi.fn(),
    routerPost: vi.fn(),
    routerPatch: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { get: spies.routerGet, visit: spies.routerVisit, delete: spies.routerDelete, on: vi.fn(() => () => {}), post: spies.routerPost, patch: spies.routerPatch },
    usePage: () => ({ url: '/', props: { flash: {}, errors: {} } }),
    useForm: (data: Record<string, unknown>) => ({
        ...data, processing: false, errors: {},
        post: spies.formPost, patch: spies.formPatch, delete: spies.formDelete,
        reset: spies.formReset, clearErrors: spies.formClearErrors,
    }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
    Head: { name: 'Head', template: '<span><slot /></span>' },
}));

import AuditIndex from '@/Pages/Admin/Audit/Index.vue';
import UsersIndex from '@/Pages/Admin/Users/Index.vue';
import UsersEdit from '@/Pages/Admin/Users/Edit.vue';
import GroupsIndex from '@/Pages/Admin/Groups/Index.vue';
import { useDialogHost } from '@/composables/useConfirm';

beforeEach(() => Object.values(spies).forEach((s) => s.mockClear()));

describe('Admin/Audit', () => {
    const logs = [{ id: 1, at: 'now', user: 'A', action: 'file.upload', file: 'x', ip: '127.0.0.1', meta: { size: 9 } }];

    it('renders events and the action badge', () => {
        const wrapper = mount(AuditIndex, { props: { logs } });
        expect(wrapper.text()).toContain('1 event(s).');
        expect(wrapper.text()).toContain('file.upload');
    });

    it('Filter submits to /audit and Clear resets', async () => {
        const wrapper = mount(AuditIndex, { props: { logs, filters: { action: 'upload', from: null, to: null } } });
        await wrapper.find('form').trigger('submit');
        expect(spies.routerGet).toHaveBeenCalledWith('/audit', expect.objectContaining({ action: 'upload' }), expect.anything());
        const clear = wrapper.findAll('button').find((b) => b.text() === 'Clear');
        await clear!.trigger('click');
        expect(spies.routerGet).toHaveBeenLastCalledWith('/audit', {}, expect.anything());
    });

    it('toggles meta detail', async () => {
        const wrapper = mount(AuditIndex, { props: { logs } });
        const more = wrapper.findAll('button').find((b) => b.text() === 'more');
        await more!.trigger('click');
        expect(wrapper.findAll('button').some((b) => b.text() === 'less')).toBe(true);
    });
});

describe('Admin/Users', () => {
    it('Index renders users and Edit navigates', async () => {
        const users = [{ id: 7, name: 'Bo', email: 'bo@x.test', group: null, is_admin: true }];
        const wrapper = mount(UsersIndex, { props: { users } });
        // The DataTable stub only renders custom #cell slots; is_admin → "Admin" badge.
        expect(wrapper.text()).toContain('Admin');
        const edit = wrapper.findAll('button').find((b) => b.text().includes('Edit'));
        await edit!.trigger('click');
        expect(spies.routerVisit).toHaveBeenCalledWith('/users/7/edit');
    });

    it('Edit submits a PATCH to the user', async () => {
        const wrapper = mount(UsersEdit, { props: { user: { id: 7, name: 'Bo', email: 'bo@x.test', group_id: null, is_admin: false }, groups: [{ id: 1, name: 'Staff' }] } });
        await wrapper.find('form').trigger('submit');
        expect(spies.formPatch).toHaveBeenCalledWith('/admin/users/7', expect.anything());
    });
});

describe('Admin/Groups', () => {
    const groups = [{ id: 3, name: 'Ops', users_count: 2 }];

    it('New group prompts for a name then posts to /groups', async () => {
        const wrapper = mount(GroupsIndex, { props: { groups } });
        await wrapper.get('[data-testid="new-group"]').trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.state.inputValue = 'Dev';
        host.accept();
        await flushPromises();
        expect(spies.routerPost).toHaveBeenCalledWith('/groups', { name: 'Dev' }, expect.anything());
    });

    it('Rename prompts (prefilled) then patches the group', async () => {
        const wrapper = mount(GroupsIndex, { props: { groups } });
        const rename = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Rename Ops');
        await rename!.trigger('click');
        const host = useDialogHost();
        expect(host.state.inputValue).toBe('Ops');
        host.state.inputValue = 'Operations';
        host.accept();
        await flushPromises();
        expect(spies.routerPatch).toHaveBeenCalledWith('/groups/3', { name: 'Operations' }, expect.anything());
    });

    it('Delete confirms then issues a DELETE', async () => {
        const wrapper = mount(GroupsIndex, { props: { groups } });
        const trash = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Delete Ops');
        await trash!.trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(spies.routerDelete).toHaveBeenCalledWith('/groups/3', expect.anything());
    });

    it('delete button is disabled while a delete is in flight', async () => {
        // routerDelete stalls — never calls onFinish.
        spies.routerDelete.mockImplementationOnce((_url: string, opts: { onFinish?: () => void }) => {
            // do nothing — simulate pending request
            void opts;
        });
        const wrapper = mount(GroupsIndex, { props: { groups } });
        const iconButtons = wrapper.findAll('button').filter((b) => b.element.querySelector('.bi') && b.text() === '');
        const trashBtn = iconButtons[iconButtons.length - 1];
        await trashBtn.trigger('click');
        const host = useDialogHost();
        host.accept();
        await flushPromises();
        // Button must be disabled while in flight.
        expect(trashBtn.element.disabled).toBe(true);
        // A second click must not fire a second DELETE.
        await trashBtn.trigger('click');
        await flushPromises();
        expect(spies.routerDelete).toHaveBeenCalledTimes(1);
    });
});
