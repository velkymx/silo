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

// VibeDataTable column definitions — declared inside <script setup> so the
// `:items` array types line up with each column's `key`.
const grantColumns = [
    { key: 'subject' as const, label: '', sortable: false, searchable: false, class: 'text-nowrap' },
    { key: 'ability' as const, label: 'Access', sortable: false, searchable: false },
    { key: 'actions' as const, label: '', sortable: false, searchable: false, class: 'text-end' },
];
const inheritedColumns = [
    { key: 'subject' as const, label: '', sortable: false, searchable: false, class: 'text-nowrap' },
    { key: 'ability' as const, label: 'Access', sortable: false, searchable: false },
    { key: 'source' as const, label: 'Inherited from', sortable: false, searchable: false },
];
const linkColumns = [
    { key: 'url' as const, label: 'Public link', sortable: false, searchable: false },
    { key: 'actions' as const, label: '', sortable: false, searchable: false, class: 'text-end text-nowrap' },
];

const grants = ref<Grant[]>([]);
const inherited = ref<Inherited[]>([]);
const groups = ref<Option[]>([]);
const links = ref<Link[]>([]);
const error = ref('');
const busy = ref(false);
const removingGrantId = ref<number | null>(null);
const revokingLinkId = ref<number | null>(null);
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
    // Accept comma- or whitespace-separated emails so the user can paste a
    // distribution list instead of inviting one at a time.
    const emails = (grant.value.subject_type === 'user' ? grant.value.email : '')
        .split(/[\s,]+/).map((e) => e.trim()).filter(Boolean);
    if (grant.value.subject_type === 'user' && !emails.length) {
        error.value = 'Enter at least one email address.';
        return;
    }
    busy.value = true;
    try {
        for (const email of emails) {
            const payload = { ...grant.value, email };
            const data = await http.post<{ permissions: Grant[] }>(`/files/${props.item.id}/permissions`, payload);
            grants.value = data.permissions;
        }
        grant.value = blankGrant();
    } catch (e) {
        const errs = e instanceof HttpError ? (e.data as { errors?: Record<string, string[]> })?.errors : null;
        error.value = errs ? Object.values(errs).flat().join(' ') : 'Could not add grant.';
    } finally {
        busy.value = false;
    }
}

async function removeGrant(id: number): Promise<void> {
    if (!props.item || removingGrantId.value !== null) return;
    removingGrantId.value = id;
    try {
        const data = await http.del<{ permissions: Grant[] }>(`/files/${props.item.id}/permissions/${id}`);
        grants.value = data.permissions;
    } catch {
        error.value = 'Could not remove access. Please try again.';
    } finally {
        removingGrantId.value = null;
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
    if (!props.item || revokingLinkId.value !== null) return;
    revokingLinkId.value = id;
    try {
        const data = await http.del<{ links: Link[] }>(`/files/${props.item.id}/links/${id}`);
        links.value = data.links;
    } catch {
        error.value = 'Could not revoke link. Please try again.';
    } finally {
        revokingLinkId.value = null;
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
        <VibeDataTable
            v-if="grants.length"
            :items="grants"
            :columns="grantColumns"
            row-key="id"
            small
            :searchable="false"
            :empty-text="'No direct grants on this item.'"
        >
            <template #cell(subject)="{ item }">
                <VibeIcon :icon="item.subject_type === 'group' ? 'people' : 'person'" class="me-1" />{{ item.subject_label }}
            </template>
            <template #cell(ability)="{ item }">
                <VibeBadge variant="secondary">{{ item.ability }}</VibeBadge>
            </template>
            <template #cell(actions)="{ item }">
                <VibeButton variant="danger" size="sm" outline :disabled="removingGrantId !== null" :aria-label="`Remove access for ${item.subject_label}`" @click="removeGrant(item.id)"><VibeIcon icon="x" /></VibeButton>
            </template>
        </VibeDataTable>
        <p v-else class="text-muted small">No direct grants on this item.</p>

        <template v-if="inherited.length">
            <h6 class="text-muted">Inherited from parent folders</h6>
            <VibeDataTable
                :items="inherited"
                :columns="inheritedColumns"
                :row-key="(g: Inherited) => `${g.subject_type}-${g.subject_label}-${g.ability}`"
                small
                :searchable="false"
            >
                <template #cell(subject)="{ item }">
                    <VibeIcon :icon="item.subject_type === 'group' ? 'people' : 'person'" class="me-1" />{{ item.subject_label }}
                </template>
                <template #cell(ability)="{ item }">
                    <VibeBadge variant="light" class="text-dark border">{{ item.ability }}</VibeBadge>
                </template>
                <template #cell(source)="{ item }">
                    <VibeIcon icon="folder" class="me-1" />{{ item.source }}
                </template>
            </VibeDataTable>
        </template>

        <hr>
        <h6 class="text-muted">Grant access</h6>
        <VibeAlert v-if="error" variant="danger">{{ error }}</VibeAlert>
        <div class="row g-2">
            <div class="col-5"><VibeFormSelect v-model="grant.subject_type" :options="subjectTypeOptions" aria-label="Grant to" /></div>
            <div class="col-7">
                <VibeFormInput v-if="grant.subject_type === 'user'" v-model="grant.email" type="email" placeholder="one@example.com, two@example.com" aria-label="User email" />
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
            <VibeDataTable
                v-if="links.length"
                :items="links"
                :columns="linkColumns"
                row-key="id"
                small
                :searchable="false"
                :empty-text="'No public links.'"
            >
                <template #cell(url)="{ item }">
                    <a :href="item.url" target="_blank" rel="noopener noreferrer" class="small text-truncate d-block" style="max-width: 220px">{{ item.url }}</a>
                    <div class="small text-muted">
                        <span v-if="item.protected"><VibeIcon icon="lock" /> password · </span>
                        <span>{{ item.allow_download ? 'download' : 'view only' }}</span>
                        <span v-if="item.expires_at"> · expires {{ item.expires_at }}</span>
                        <span v-if="item.expired" class="text-danger"> · expired</span>
                    </div>
                </template>
                <template #cell(actions)="{ item }">
                    <VibeButton variant="secondary" size="sm" outline aria-label="Copy link" @click="copyLink(item.url, item.id)">
                        <VibeIcon :icon="copied === item.id ? 'check' : 'clipboard'" />
                    </VibeButton>
                    <VibeButton variant="danger" size="sm" outline class="ms-1" :disabled="revokingLinkId !== null" aria-label="Revoke link" @click="revokeLink(item.id)"><VibeIcon icon="x" /></VibeButton>
                </template>
            </VibeDataTable>
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
