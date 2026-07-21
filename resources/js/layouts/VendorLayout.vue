<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import FlashMessages from '@/components/FlashMessages.vue';
import type { SharedData } from '@/types';
import { Link, router, usePage } from '@inertiajs/vue3';
import { LayoutGrid, ListChecks, LogOut } from 'lucide-vue-next';
import { computed } from 'vue';

const page = usePage<SharedData>();
const user = computed(() => page.props.auth.user);

function logout() {
    router.post('/logout');
}
</script>

<template>
    <div class="min-h-screen bg-muted/30 text-foreground">
        <header class="sticky top-0 z-30 border-b border-sidebar-border/70 bg-background/95 backdrop-blur">
            <div class="mx-auto flex h-16 max-w-5xl items-center justify-between gap-3 px-4">
                <Link href="/vendor" class="flex items-center gap-2">
                    <span class="flex size-9 items-center justify-center rounded-lg bg-brand-yellow text-brand-maroon ring-1 ring-black/5">
                        <AppLogoIcon class="size-6" />
                    </span>
                    <span class="font-extrabold tracking-tight">
                        <span>Car</span><span class="text-brand-yellow">4</span><span>Sales</span>
                        <span class="ml-1 text-xs font-medium uppercase tracking-wider text-muted-foreground">Vendor Portal</span>
                    </span>
                </Link>

                <nav class="flex items-center gap-1 text-sm">
                    <Link href="/vendor" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-muted-foreground transition hover:bg-muted hover:text-foreground">
                        <LayoutGrid class="size-4" /> <span class="hidden sm:inline">Dashboard</span>
                    </Link>
                    <Link href="/vendor/submissions" class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-muted-foreground transition hover:bg-muted hover:text-foreground">
                        <ListChecks class="size-4" /> <span class="hidden sm:inline">My Submissions</span>
                    </Link>
                    <div class="mx-1 hidden h-6 w-px bg-border sm:block" />
                    <span class="hidden px-2 text-sm text-muted-foreground sm:inline">{{ user?.name }}</span>
                    <button class="inline-flex items-center gap-1.5 rounded-md px-3 py-2 text-muted-foreground transition hover:bg-muted hover:text-foreground" @click="logout">
                        <LogOut class="size-4" /> <span class="hidden sm:inline">Logout</span>
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-5xl px-4 py-6">
            <slot />
        </main>

        <FlashMessages />
    </div>
</template>
