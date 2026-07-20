<script setup lang="ts">
import { Button } from '@/components/ui/button';
import type { Paginated } from '@/types';
import { Link } from '@inertiajs/vue3';

defineProps<{
    paginator: Paginated<unknown>;
}>();
</script>

<template>
    <div v-if="paginator.total > 0" class="flex items-center justify-between gap-4 px-2 py-3">
        <p class="text-sm text-muted-foreground">
            Showing {{ paginator.from ?? 0 }}–{{ paginator.to ?? 0 }} of {{ paginator.total }}
        </p>
        <div class="flex flex-wrap items-center gap-1">
            <template v-for="(link, index) in paginator.links" :key="index">
                <Button v-if="link.url" :variant="link.active ? 'default' : 'outline'" size="sm" as-child>
                    <Link :href="link.url" preserve-scroll preserve-state>
                        <span v-html="link.label" />
                    </Link>
                </Button>
                <Button v-else variant="ghost" size="sm" disabled>
                    <span v-html="link.label" />
                </Button>
            </template>
        </div>
    </div>
</template>
