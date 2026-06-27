<script setup lang="ts">
import { ref, watch, onBeforeUnmount } from 'vue';
import { http, HttpError } from '../lib/http';

interface FileLike { id: number; name: string; is_dir?: boolean }
interface Grant { id: number; subject_type: string; subject_label: string; ability: string }
interface Inherited { subject_type: string; subject_label: string; ability: string; source: string }
interface Link { id: number; url: string; protected?: boolean; allow_download?: boolean; expires_at?: string; expired?: boolean }
interface Option { value: number | string; text: string }

const open = defineModel<boolean>({ required: true });
const props = defineProps<{ item: FileLike | null }>();

const grants = ref<Grant[]>([]);
const inherited = ref<Inherited[]>([]);
const groups = ref<Option[]>([]);
const links = ref<Link[]>([]);
const error = ref('');
const busy = ref(false);
const copied = ref<number | null>(null);
let loadSeq = 0;

const abilityOptions = ['read', 'write', 'delete', 'share'];
const subjectTypeOptions = [
    { value: 'user', text: 'User (email)' },
    { value: 'group', text: 'Group' },
];

const blankGrant = () => ({ subject_type: 'user', email: '', group_id: null as number | null, abilities: ['read'] });
const grant = ref(blankGrant());
const blankLink = () => ({ allow_download: true, expires_in_days: null as number | null, password: '' });
const linkForm = ref(blankLink());

function reset(): void {
    grant.value = blankGrant();
    linkForm.value = blankLink();
    grants.value = [];
    inherited.value = [];
    links.value = [];
    error.value = '';
}

async function load(item: FileLike): Promise<void> {
    const seq = ++loadSeq;
    const [perms, lk] = await Promise.all([
        http.get<{ permissions: Grant[]; inherited?: Inherited[]; groups: { id: number; name: string }[] }>(`/files/${item.id}/permissions`),
        http.get<{ links: Link[] }>(`/files/${item.id}/links`),
    ]);
    if (seq !== loadSeq) return;
    grants.value = perms.permissions;
    inherited.value = perms.inherited ?? [];
    groups.value = perms.groups.map((g) => ({ value: g.id, text: g.name }));
    links.value = lk.links;
}

watch(open, (v) => {
    if (v && props.item) {
        reset();
        load(props.item);
    }
});

function toggleAbility(ability: string): void {
    const set = new Set(grant.value.abilities);
    set.has(ability) ? set.delete(ability) : set.add(ability);
    grant.value.abilities = [...set];
}

async function addGrant(): Promise<void> {
    if (!props.item) return;
    error.value = '';
    busy.value = true;
    try {
        const data = await http.post<{ permissions: Grant[] }>(`/files/${props.item.id}/permissions`, grant.value);
        grants.value = data.permissions;
        grant.value = blankGrant();
    } catch (e) {
        const errs = e instanceof HttpError ? (e.data as { errors?: Record<string, string[]> })?.errors : null;
        error.value = errs ? Object.values(errs).flat().join(' ') : 'Could not add grant.';
    } finally {
        busy.value = false;
    }
}

async function removeGrant(id: number): Promise<void> {
    if (!props.item) return;
    try {
        const data = await http.del<{ permissions: Grant[] }>(`/files/${props.item.id}/permissions/${id}`);
        grants.value = data.permissions;
    } catch {
        error.value = 'Could not remove access. Please try again.';
    }
}

async function createLink(): Promise<void> {
    if (!props.item) return;
    busy.value = true;
    try {
        const data = await http.post<{ links: Link[] }>(`/files/${props.item.id}/links`, {
            allow_download: linkForm.value.allow_download,
            expires_in_days: linkForm.value.expires_in_days || null,
            password: linkForm.value.password || null,
        });
        links.value = data.links;
        linkForm.value = blankLink();
    } catch {
        error.value = 'Could not create link. Please try again.';
    } finally {
        busy.value = false;
    }
}

async function revokeLink(id: number): Promise<void> {
    if (!props.item) return;
    try {
        const data = await http.del<{ links: Link[] }>(`/files/${props.item.id}/links/${id}`);
        links.value = data.links;
    } catch {
        error.value = 'Could not revoke link. Please try again.';
    }
}

let copiedTimer: ReturnType<typeof setTimeout> | null = null;

function copyLink(url: string, id: number): void {
    navigator.clipboard?.writeText(url);
    copied.value = id;
    if (copiedTimer) clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => { copied.value = null; copiedTimer = null; }, 1500);
}

onBeforeUnmount(() => {
    if (copiedTimer) clearTimeout(copiedTimer);
});
</script>

<template>
    <VibeModal v-model="open" :title="`Share — ${item?.name || ''}`" fullscreen>
        <h6 class="text-muted">People &amp; groups with access</h6>
        <table v-if="grants.length" class="table table-sm align-middle">
            <tbody>
                <tr v-for="g in grants" :key="g.id">
                    <td><VibeIcon :icon="g.subject_type === 'group' ? 'people' : 'person'" class="me-1" />{{ g.subject_label }}</td>
                    <td><VibeBadge variant="secondary">{{ g.ability }}</VibeBadge></td>
                    <td class="text-end">
                        <VibeButton variant="danger" size="sm" outline :aria-label="`Remove access for ${g.subject_label}`" @click="removeGrant(g.id)"><VibeIcon icon="x" /></VibeButton>
                    </td>
                </tr>
            </tbody>
        </table>
        <p v-else class="text-muted small">No direct grants on this item.</p>

        <template v-if="inherited.length">
            <h6 class="text-muted">Inherited from parent folders</h6>
            <table class="table table-sm align-middle">
                <tbody>
                    <tr v-for="(g, i) in inherited" :key="i" class="text-muted">
                        <td><VibeIcon :icon="g.subject_type === 'group' ? 'people' : 'person'" class="me-1" />{{ g.subject_label }}</td>
                        <td><VibeBadge variant="light" class="text-dark border">{{ g.ability }}</VibeBadge></td>
                        <td class="small"><VibeIcon icon="folder" class="me-1" />{{ g.source }}</td>
                    </tr>
                </tbody>
            </table>
        </template>

        <hr>
        <h6 class="text-muted">Grant access</h6>
        <VibeAlert v-if="error" variant="danger">{{ error }}</VibeAlert>
        <div class="row g-2">
            <div class="col-5"><VibeFormSelect v-model="grant.subject_type" :options="subjectTypeOptions" aria-label="Grant to" /></div>
            <div class="col-7">
                <VibeFormInput v-if="grant.subject_type === 'user'" v-model="grant.email" type="email" placeholder="person@example.com" aria-label="User email" />
                <VibeFormSelect v-else v-model="grant.group_id" :options="groups" placeholder="Choose a group…" aria-label="Group" />
            </div>
        </div>
        <div class="d-flex flex-wrap gap-3 mt-3">
            <VibeFormCheckbox
                v-for="a in abilityOptions"
                :key="a"
                :model-value="grant.abilities.includes(a)"
                :label="a"
                @update:model-value="toggleAbility(a)"
            />
        </div>
        <div class="text-end mt-3">
            <VibeButton variant="primary" :disabled="busy" @click="addGrant">Grant</VibeButton>
        </div>

        <template v-if="!item?.is_dir">
            <hr>
            <h6 class="text-muted">Public links</h6>
            <table v-if="links.length" class="table table-sm align-middle">
                <tbody>
                    <tr v-for="link in links" :key="link.id">
                        <td class="text-truncate" style="max-width: 220px">
                            <a :href="link.url" target="_blank" rel="noopener noreferrer" class="small">{{ link.url }}</a>
                            <div class="small text-muted">
                                <span v-if="link.protected"><VibeIcon icon="lock" /> password · </span>
                                <span>{{ link.allow_download ? 'download' : 'view only' }}</span>
                                <span v-if="link.expires_at"> · expires {{ link.expires_at }}</span>
                                <span v-if="link.expired" class="text-danger"> · expired</span>
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            <VibeButton variant="secondary" size="sm" outline aria-label="Copy link" @click="copyLink(link.url, link.id)">
                                <VibeIcon :icon="copied === link.id ? 'check' : 'clipboard'" />
                            </VibeButton>
                            <VibeButton variant="danger" size="sm" outline class="ms-1" aria-label="Revoke link" @click="revokeLink(link.id)"><VibeIcon icon="x" /></VibeButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-muted small">No public links.</p>

            <div class="row g-2 align-items-center">
                <div class="col-auto"><VibeFormCheckbox v-model="linkForm.allow_download" label="Allow download" /></div>
                <div class="col"><VibeFormInput v-model="linkForm.expires_in_days" type="number" placeholder="Expires in N days (optional)" aria-label="Link expiry in days" /></div>
                <div class="col"><VibeFormInput v-model="linkForm.password" type="password" placeholder="Password (optional)" aria-label="Link password" /></div>
                <div class="col-auto"><VibeButton variant="primary" :disabled="busy" @click="createLink">Create link</VibeButton></div>
            </div>
        </template>
        <template #footer>
            <VibeButton variant="secondary" outline @click="open = false">Cancel</VibeButton>
        </template>
    </VibeModal>
</template>
