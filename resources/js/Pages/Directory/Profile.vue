<script setup lang="ts">
import { ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import UserAvatar from '../../Components/UserAvatar.vue';
import Wall from '../../Components/Wall/Wall.vue';
import type { WallPostShape } from '../../Components/Wall/WallPostCard.vue';

interface Person {
    id: number;
    name: string;
    title: string | null;
    department: string | null;
    phone: string | null;
    email: string | null;
    bio: string | null;
    location: string | null;
    start_date: string | null;
    group: string | null;
    manager: { id: number; name: string } | null;
    avatar_url: string | null;
}

defineProps<{ person: Person; wall: WallPostShape[] }>();

const activePane = ref('contents');
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="false" :folders-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <Link href="/directory" class="btn btn-light btn-sm">
                    <VibeIcon icon="chevron-left" class="me-1" />Directory
                </Link>
                <h1 class="h5 mb-0 ms-2 d-flex align-items-center gap-2 text-truncate">
                    <VibeIcon icon="person-rolodex" class="text-primary" />{{ person.name }}
                </h1>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1">
                <div class="profile-page px-4 py-3">
                    <div class="row g-4">
                        <div class="col-12 col-lg-4">
                            <div class="card">
                                <div class="card-body text-center">
                                    <UserAvatar :user="person" :size="112" class="mb-3" />
                                    <h2 class="h4 mb-0">{{ person.name }}</h2>
                                    <p v-if="person.title" class="text-muted mb-2">
                                        {{ person.title }}<span v-if="person.department"> · {{ person.department }}</span>
                                    </p>
                                    <p v-if="person.bio" class="mb-0">{{ person.bio }}</p>
                                </div>
                                <dl class="card-body row mb-0 small border-top">
                                    <template v-if="person.email"><dt class="col-4 text-muted">Email</dt><dd class="col-8 text-break"><a :href="`mailto:${person.email}`">{{ person.email }}</a></dd></template>
                                    <template v-if="person.phone"><dt class="col-4 text-muted">Phone</dt><dd class="col-8">{{ person.phone }}</dd></template>
                                    <template v-if="person.location"><dt class="col-4 text-muted">Location</dt><dd class="col-8">{{ person.location }}</dd></template>
                                    <template v-if="person.group"><dt class="col-4 text-muted">Group</dt><dd class="col-8">{{ person.group }}</dd></template>
                                    <template v-if="person.manager"><dt class="col-4 text-muted">Reports to</dt><dd class="col-8"><Link :href="`/directory/${person.manager.id}`">{{ person.manager.name }}</Link></dd></template>
                                    <template v-if="person.start_date"><dt class="col-4 text-muted">Started</dt><dd class="col-8">{{ person.start_date }}</dd></template>
                                </dl>
                            </div>
                        </div>
                        <div class="col-12 col-lg-8">
                            <Wall :posts="wall" :wall-user-id="person.id" />
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </ShellLayout>
</template>

<style scoped>
.profile-page {
    width: 100%;
}
</style>
