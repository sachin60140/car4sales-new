<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import type { AppNotification, BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { CheckCheck } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{
    notifications: Paginated<AppNotification>;
    unread: number;
    filter: string;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Notifications', href: '/admin/notifications' },
];

const activeFilter = computed(() => props.filter);

const levelStyle: Record<string, string> = {
    info: 'bg-sky-500',
    success: 'bg-emerald-500',
    warning: 'bg-brand-orange',
    critical: 'bg-brand-red',
};

function setFilter(f: string) {
    router.get('/admin/notifications', f === 'all' ? {} : { filter: f }, { preserveState: true, replace: true });
}

function open(n: AppNotification) {
    router.post(`/admin/notifications/${n.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (n.action_url) router.visit(n.action_url);
        },
    });
}

function markRead(n: AppNotification) {
    router.post(`/admin/notifications/${n.id}/read`, {}, { preserveScroll: true });
}

function markAllRead() {
    router.post('/admin/notifications/read-all', {}, { preserveScroll: true });
}

function fmt(iso: string): string {
    return new Date(iso).toLocaleString();
}
</script>

<template>
    <Head title="Notifications" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Notifications</h1>
                    <p class="text-sm text-muted-foreground">{{ unread }} unread</p>
                </div>
                <div class="flex items-center gap-2">
                    <div class="flex rounded-md border border-input p-0.5 text-sm">
                        <button
                            class="rounded px-3 py-1"
                            :class="activeFilter === 'all' ? 'bg-muted font-medium' : 'text-muted-foreground'"
                            @click="setFilter('all')"
                        >All</button>
                        <button
                            class="rounded px-3 py-1"
                            :class="activeFilter === 'unread' ? 'bg-muted font-medium' : 'text-muted-foreground'"
                            @click="setFilter('unread')"
                        >Unread</button>
                    </div>
                    <Button v-if="unread > 0" size="sm" variant="outline" @click="markAllRead">
                        <CheckCheck class="mr-1 size-4" /> Mark all read
                    </Button>
                </div>
            </div>

            <div class="overflow-hidden rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <div v-if="notifications.data.length === 0" class="px-4 py-16 text-center text-muted-foreground">
                    No notifications{{ activeFilter === 'unread' ? ' unread' : '' }}.
                </div>
                <ul v-else class="divide-y">
                    <li
                        v-for="n in notifications.data"
                        :key="n.id"
                        class="flex items-start gap-3 px-4 py-3 transition hover:bg-muted/30"
                        :class="n.read ? 'opacity-70' : 'bg-muted/20'"
                    >
                        <span class="mt-1.5 size-2.5 shrink-0 rounded-full" :class="levelStyle[n.level] ?? 'bg-muted-foreground'" />
                        <button class="flex-1 text-left" @click="open(n)">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-medium">{{ n.title }}</span>
                                <span v-if="!n.read" class="rounded-full bg-brand-red/15 px-1.5 py-0.5 text-[10px] font-medium text-brand-red">New</span>
                            </div>
                            <p v-if="n.body" class="mt-0.5 text-sm text-muted-foreground">{{ n.body }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">{{ fmt(n.created_at) }}</p>
                        </button>
                        <div class="flex shrink-0 items-center gap-2">
                            <Button v-if="n.action_url" size="sm" variant="ghost" as-child>
                                <Link :href="n.action_url" @click="markRead(n)">Open</Link>
                            </Button>
                            <button v-if="!n.read" class="text-xs text-muted-foreground hover:text-foreground" @click="markRead(n)">Mark read</button>
                        </div>
                    </li>
                </ul>
            </div>

            <Pagination :paginator="notifications" />
        </div>
    </AppLayout>
</template>
