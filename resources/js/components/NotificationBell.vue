<script setup lang="ts">
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { AppNotification, SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Bell } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const unread = computed(() => page.props.notifications?.unread ?? 0);
const recent = computed<AppNotification[]>(() => page.props.notifications?.recent ?? []);

const levelDot: Record<string, string> = {
    info: 'bg-sky-500',
    success: 'bg-emerald-500',
    warning: 'bg-brand-orange',
    critical: 'bg-brand-red',
};

function open(n: AppNotification) {
    router.post(`/admin/notifications/${n.id}/read`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (n.action_url) router.visit(n.action_url);
        },
    });
}

function markAllRead() {
    router.post('/admin/notifications/read-all', {}, { preserveScroll: true });
}

function timeAgo(iso: string): string {
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'just now';
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.floor(hrs / 24)}d ago`;
}
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger
            class="relative inline-flex size-9 items-center justify-center rounded-md text-muted-foreground transition hover:bg-muted hover:text-foreground focus:outline-none focus-visible:ring-2 focus-visible:ring-ring"
            aria-label="Notifications"
        >
            <Bell class="size-5" />
            <span
                v-if="unread > 0"
                class="absolute -right-0.5 -top-0.5 flex min-w-[18px] items-center justify-center rounded-full bg-brand-red px-1 text-[10px] font-bold leading-4 text-white"
            >{{ unread > 99 ? '99+' : unread }}</span>
        </DropdownMenuTrigger>

        <DropdownMenuContent align="end" class="w-80">
            <div class="flex items-center justify-between px-2 py-1.5">
                <DropdownMenuLabel class="p-0 text-sm font-semibold">Notifications</DropdownMenuLabel>
                <button v-if="unread > 0" class="text-xs text-brand-red hover:underline" @click="markAllRead">Mark all read</button>
            </div>
            <DropdownMenuSeparator />

            <div v-if="recent.length === 0" class="px-3 py-6 text-center text-sm text-muted-foreground">
                You're all caught up.
            </div>

            <template v-else>
                <DropdownMenuItem
                    v-for="n in recent"
                    :key="n.id"
                    class="flex cursor-pointer flex-col items-start gap-0.5 py-2"
                    :class="n.read ? 'opacity-60' : ''"
                    @click="open(n)"
                >
                    <div class="flex w-full items-center gap-2">
                        <span class="size-2 shrink-0 rounded-full" :class="levelDot[n.level] ?? 'bg-muted-foreground'" />
                        <span class="flex-1 truncate text-sm font-medium">{{ n.title }}</span>
                        <span class="shrink-0 text-[10px] text-muted-foreground">{{ timeAgo(n.created_at) }}</span>
                    </div>
                    <span v-if="n.body" class="line-clamp-2 pl-4 text-xs text-muted-foreground">{{ n.body }}</span>
                </DropdownMenuItem>
            </template>

            <DropdownMenuSeparator />
            <DropdownMenuItem as-child class="cursor-pointer justify-center text-sm font-medium">
                <Link href="/admin/notifications">View all notifications</Link>
            </DropdownMenuItem>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
