<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    job: Record<string, any>;
    can: { approve: boolean; update: boolean };
}>();

const j = computed(() => props.job);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Workshop', href: '/admin/workshop' },
    { title: j.value.job_number, href: '#' },
];

function money(x: unknown): string {
    if (x === null || x === undefined || x === '') return '—';
    return '₹' + Number(x).toLocaleString('en-IN');
}

const approveForm = useForm({ approved_total: Number(j.value.estimate_total) });
function approve() {
    approveForm.post(`/admin/workshop/${j.value.id}/approve`, { preserveScroll: true });
}
function start() {
    router.post(`/admin/workshop/${j.value.id}/start`, {}, { preserveScroll: true });
}

interface ItemActual {
    id: number;
    actual_amount: number;
    [key: string]: number;
}

const completeForm = useForm<{ qc: string; actual_total: number; items: ItemActual[] }>({
    qc: 'passed',
    actual_total: Number(j.value.approved_total ?? j.value.estimate_total),
    items: (j.value.items ?? []).map((it: Record<string, any>) => ({ id: it.id, actual_amount: Number(it.approved_amount ?? it.estimate) })),
});
function complete() {
    completeForm.actual_total = completeForm.items.reduce((s: number, i: ItemActual) => s + Number(i.actual_amount || 0), 0);
    completeForm.post(`/admin/workshop/${j.value.id}/complete`, { preserveScroll: true });
}

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    approved: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    in_progress: 'bg-amber-100 text-amber-700 dark:bg-amber-900/40 dark:text-amber-400',
    qc_passed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    qc_failed: 'bg-brand-red/15 text-brand-red',
};

const itemLabel = (id: number) => (j.value.items ?? []).find((x: any) => x.id === id)?.description ?? '';
</script>

<template>
    <Head :title="job.job_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ job.job_number }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusStyle[job.status] ?? 'bg-muted text-muted-foreground'">
                            {{ (job.status ?? '').replace(/_/g, ' ') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <Link v-if="job.vehicle" :href="`/admin/inventory/${job.vehicle.id}`" class="underline">{{ job.vehicle.stock_number }}</Link>
                        · {{ [job.vehicle?.make, job.vehicle?.model].filter(Boolean).join(' ') }} · {{ job.vendor?.name ?? 'Internal' }}
                    </p>
                </div>
                <Button variant="outline" as-child><Link href="/admin/workshop">Back</Link></Button>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Estimate</p><p class="text-lg font-bold">{{ money(job.estimate_total) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Approved</p><p class="text-lg font-bold">{{ money(job.approved_total) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Actual</p><p class="text-lg font-bold">{{ money(job.actual_total) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">QC</p><p class="text-lg font-bold capitalize">{{ job.qc_status ?? '—' }}</p></CardContent></Card>
            </div>

            <Card>
                <CardHeader><CardTitle>Work Items</CardTitle></CardHeader>
                <CardContent>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="py-2 pr-3 font-medium">Item</th><th class="py-2 pr-3 font-medium">Type</th>
                                <th class="py-2 pr-3 font-medium">Estimate</th><th class="py-2 pr-3 font-medium">Approved</th><th class="py-2 font-medium">Actual</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in job.items" :key="item.id" class="border-b last:border-0">
                                <td class="py-2 pr-3">{{ item.description }}<span v-if="item.defect" class="text-muted-foreground"> — {{ item.defect }}</span></td>
                                <td class="py-2 pr-3 capitalize">{{ item.work_type }}</td>
                                <td class="py-2 pr-3">{{ money(item.estimate) }}</td>
                                <td class="py-2 pr-3">{{ money(item.approved_amount) }}</td>
                                <td class="py-2">{{ money(item.actual_amount) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- Lifecycle actions -->
            <Card v-if="can.approve && job.status === 'draft'">
                <CardHeader><CardTitle>Approve Job</CardTitle></CardHeader>
                <CardContent class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1">
                        <Label class="text-xs">Approved Budget (₹)</Label>
                        <Input v-model.number="approveForm.approved_total" type="number" min="0" class="h-9 w-40" />
                    </div>
                    <Button :disabled="approveForm.processing" @click="approve">Approve Job</Button>
                    <p class="text-xs text-muted-foreground">Approval moves the vehicle to Under Refurbishment.</p>
                </CardContent>
            </Card>

            <Card v-if="can.update && job.status === 'approved'">
                <CardContent class="flex items-center gap-3 py-4">
                    <Button @click="start">Start Work</Button>
                    <p class="text-sm text-muted-foreground">Mark the job in progress.</p>
                </CardContent>
            </Card>

            <Card v-if="can.update && (job.status === 'in_progress' || job.status === 'qc_failed')">
                <CardHeader><CardTitle>Complete &amp; QC</CardTitle></CardHeader>
                <CardContent class="grid gap-3">
                    <div v-for="item in completeForm.items" :key="item.id" class="flex items-center gap-3">
                        <span class="flex-1 text-sm">{{ itemLabel(item.id) }}</span>
                        <Input v-model.number="item.actual_amount" type="number" min="0" placeholder="Actual" class="h-9 w-32" />
                    </div>
                    <div class="flex flex-wrap items-end gap-3 border-t pt-3">
                        <div class="grid gap-1">
                            <Label class="text-xs">QC Result</Label>
                            <select v-model="completeForm.qc" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="passed">Passed</option><option value="failed">Failed (rework)</option>
                            </select>
                        </div>
                        <Button :disabled="completeForm.processing" @click="complete">Complete Job</Button>
                        <p class="text-xs text-muted-foreground">On QC pass, the actual cost posts to the vehicle's landed cost and it returns to Ready for Sale.</p>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
