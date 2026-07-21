<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { CheckCircle2, RefreshCw, XCircle } from 'lucide-vue-next';
import { computed } from 'vue';

interface ChecklistField {
    key: string;
    label: string;
    auto: boolean;
}

const props = defineProps<{
    delivery: Record<string, any>;
    rtoCase: { id: number; rto_number: string; status: string } | null;
    checklistFields: ChecklistField[];
    can: { update: boolean; approve: boolean; print: boolean };
}>();

const d = computed(() => props.delivery);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Deliveries', href: '/admin/deliveries' },
    { title: d.value.delivery_number, href: '#' },
];

const isPending = computed(() => d.value.status === 'approval_pending');
const isApproved = computed(() => d.value.status === 'approved');
const isDelivered = computed(() => d.value.status === 'delivered');

const manualFields = computed(() => props.checklistFields.filter((f) => !f.auto));

// Manual checklist toggles.
const checksForm = useForm<Record<string, boolean>>(Object.fromEntries(manualFields.value.map((f) => [f.key, !!d.value[f.key]])));
function saveChecks() {
    checksForm.post(`/admin/deliveries/${d.value.id}/checks`, { preserveScroll: true });
}

function refreshChecklist() {
    router.post(`/admin/deliveries/${d.value.id}/refresh-checklist`, {}, { preserveScroll: true });
}

function approve() {
    router.post(`/admin/deliveries/${d.value.id}/approve`, {}, { preserveScroll: true });
}

// Handover form.
const handoverForm = useForm({
    odometer: d.value.odometer ?? null,
    fuel_level: d.value.fuel_level ?? '',
    dc_keys: !!d.value.dc_keys,
    dc_spare_key: !!d.value.dc_spare_key,
    dc_rc_copy: !!d.value.dc_rc_copy,
    dc_insurance: !!d.value.dc_insurance,
    dc_invoice: !!d.value.dc_invoice,
    dc_tool_kit: !!d.value.dc_tool_kit,
    dc_spare_wheel: !!d.value.dc_spare_wheel,
    dc_accessories: !!d.value.dc_accessories,
    remarks: d.value.remarks ?? '',
});
function completeHandover() {
    handoverForm.post(`/admin/deliveries/${d.value.id}/complete`, { preserveScroll: true });
}

function generateChallan() {
    router.post(`/admin/deliveries/${d.value.id}/challan`, {}, { preserveScroll: true });
}

const handoverItems = [
    { key: 'dc_keys', label: 'Keys' },
    { key: 'dc_spare_key', label: 'Spare Key' },
    { key: 'dc_rc_copy', label: 'RC Copy' },
    { key: 'dc_insurance', label: 'Insurance' },
    { key: 'dc_invoice', label: 'Invoice' },
    { key: 'dc_tool_kit', label: 'Tool Kit' },
    { key: 'dc_spare_wheel', label: 'Spare Wheel' },
    { key: 'dc_accessories', label: 'Accessories' },
] as const;

const statusStyle: Record<string, string> = {
    approval_pending: 'bg-brand-orange/15 text-brand-orange',
    approved: 'bg-sky-100 text-sky-700 dark:bg-sky-900/40 dark:text-sky-400',
    delivered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    cancelled: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <Head :title="delivery.delivery_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ delivery.delivery_number }}</h1>
                        <span
                            class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium"
                            :class="statusStyle[delivery.status] ?? 'bg-muted text-muted-foreground'"
                            >{{ delivery.status_label }}</span
                        >
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ delivery.customer?.name }} · {{ delivery.customer?.mobile }} ·
                        <Link v-if="delivery.booking" :href="`/admin/bookings/${delivery.booking.id}`" class="underline">{{
                            delivery.booking.booking_number
                        }}</Link>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="can.print && isDelivered" size="sm" variant="outline" @click="generateChallan">Generate Challan</Button>
                    <Button variant="outline" as-child><Link href="/admin/deliveries">Back</Link></Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Approval checklist -->
                <div class="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between">
                            <CardTitle class="text-base">Approval Checklist</CardTitle>
                            <Button v-if="can.update && !isDelivered" size="sm" variant="ghost" @click="refreshChecklist">
                                <RefreshCw class="mr-1 size-3.5" /> Refresh auto-checks
                            </Button>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">System-verified</p>
                                <ul class="space-y-1.5">
                                    <li v-for="f in checklistFields.filter((x) => x.auto)" :key="f.key" class="flex items-center gap-2 text-sm">
                                        <CheckCircle2 v-if="delivery[f.key]" class="size-4 text-emerald-600" />
                                        <XCircle v-else class="size-4 text-brand-red" />
                                        <span :class="delivery[f.key] ? '' : 'text-muted-foreground'">{{ f.label }}</span>
                                    </li>
                                </ul>
                            </div>

                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Manual verification</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label v-for="f in manualFields" :key="f.key" class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :model-value="checksForm[f.key]"
                                            :disabled="!can.update || isDelivered"
                                            @update:model-value="checksForm[f.key] = $event === true"
                                        />
                                        <span>{{ f.label }}</span>
                                    </label>
                                </div>
                                <Button v-if="can.update && !isDelivered" size="sm" class="mt-3" :disabled="checksForm.processing" @click="saveChecks"
                                    >Save checklist</Button
                                >
                            </div>

                            <div v-if="isPending && can.approve" class="rounded-lg border border-dashed p-3">
                                <p class="mb-2 text-sm" :class="delivery.checklist_complete ? 'text-emerald-600' : 'text-muted-foreground'">
                                    {{
                                        delivery.checklist_complete
                                            ? 'All items complete — ready to approve.'
                                            : 'Complete every checklist item to approve.'
                                    }}
                                </p>
                                <Button size="sm" :disabled="!delivery.checklist_complete" @click="approve">Approve Delivery</Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Handover -->
                    <Card v-if="isApproved || isDelivered">
                        <CardHeader><CardTitle class="text-base">Handover</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-1">
                                    <Label class="text-xs">Odometer (km)</Label>
                                    <Input v-model.number="handoverForm.odometer" type="number" class="h-9" :disabled="isDelivered" />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Fuel Level</Label>
                                    <Input v-model="handoverForm.fuel_level" placeholder="e.g. Half" class="h-9" :disabled="isDelivered" />
                                </div>
                            </div>
                            <div>
                                <p class="mb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Items handed over</p>
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <label v-for="item in handoverItems" :key="item.key" class="flex items-center gap-2 text-sm">
                                        <Checkbox
                                            :model-value="handoverForm[item.key]"
                                            :disabled="isDelivered"
                                            @update:model-value="handoverForm[item.key] = $event === true"
                                        />
                                        <span>{{ item.label }}</span>
                                    </label>
                                </div>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Remarks</Label>
                                <Input v-model="handoverForm.remarks" class="h-9" :disabled="isDelivered" />
                            </div>
                            <Button v-if="isApproved && can.update" :disabled="handoverForm.processing" @click="completeHandover"
                                >Complete Handover</Button
                            >
                            <p v-if="isDelivered" class="text-sm text-emerald-600">
                                Vehicle delivered on {{ new Date(delivery.delivered_at).toLocaleString() }}.
                            </p>
                        </CardContent>
                    </Card>
                </div>

                <!-- Side rail -->
                <div class="space-y-4">
                    <Card>
                        <CardHeader><CardTitle class="text-base">Vehicle</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-muted-foreground">Stock</span><span>{{ delivery.vehicle?.stock_number ?? '—' }}</span>
                            <span class="text-muted-foreground">Model</span><span>{{ delivery.vehicle?.make }} {{ delivery.vehicle?.model }}</span>
                            <span class="text-muted-foreground">Reg. No.</span><span>{{ delivery.vehicle?.registration_number ?? '—' }}</span>
                            <span class="text-muted-foreground">Colour</span><span>{{ delivery.vehicle?.color ?? '—' }}</span>
                        </CardContent>
                    </Card>

                    <Card v-if="rtoCase">
                        <CardHeader><CardTitle class="text-base">RTO Transfer</CardTitle></CardHeader>
                        <CardContent class="text-sm">
                            <Link :href="`/admin/rto-cases/${rtoCase.id}`" class="font-mono text-xs underline">{{ rtoCase.rto_number }}</Link>
                            <span class="ml-2 capitalize text-muted-foreground">{{ rtoCase.status.replace(/_/g, ' ') }}</span>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle class="text-base">Deal</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-muted-foreground">Payment Mode</span
                            ><span class="capitalize">{{ delivery.booking?.payment_mode ?? '—' }}</span>
                            <span class="text-muted-foreground">Branch</span><span>{{ delivery.branch?.name ?? '—' }}</span>
                            <span class="text-muted-foreground">Approved by</span><span>{{ delivery.approver?.name ?? '—' }}</span>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
