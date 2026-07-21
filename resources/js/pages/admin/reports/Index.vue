<script setup lang="ts">
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, ChevronRight } from 'lucide-vue-next';
import { computed } from 'vue';

interface ReportCard {
    key: string;
    label: string;
    description: string;
    group: string;
}

const props = defineProps<{
    reports: ReportCard[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Reports', href: '/admin/reports' },
];

const grouped = computed(() => {
    const map: Record<string, ReportCard[]> = {};
    for (const r of props.reports) {
        (map[r.group] ??= []).push(r);
    }
    return Object.entries(map);
});

function openReport(key: string) {
    router.get(`/admin/reports/${key}`);
}
</script>

<template>
    <Head title="Reports" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-6 p-4">
            <div>
                <h1 class="text-xl font-semibold">Reports</h1>
                <p class="text-sm text-muted-foreground">Operational and financial reports with CSV / PDF export.</p>
            </div>

            <div v-if="reports.length === 0" class="rounded-xl border border-dashed p-10 text-center text-muted-foreground">
                You don't have access to any reports.
            </div>

            <section v-for="[group, items] in grouped" :key="group" class="space-y-3">
                <h2 class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ group }}</h2>
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Card
                        v-for="r in items"
                        :key="r.key"
                        class="cursor-pointer transition hover:border-brand-red/40 hover:shadow-sm"
                        @click="openReport(r.key)"
                    >
                        <CardContent class="flex items-start gap-3 p-4">
                            <div
                                class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-brand-yellow/20 text-brand-maroon dark:text-brand-yellow"
                            >
                                <BarChart3 class="size-5" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-2">
                                    <h3 class="font-medium">{{ r.label }}</h3>
                                    <ChevronRight class="size-4 text-muted-foreground" />
                                </div>
                                <p class="mt-0.5 text-xs text-muted-foreground">{{ r.description }}</p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </section>
        </div>
    </AppLayout>
</template>
