<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    application: Record<string, any>;
    lenders: { id: number; name: string }[];
    allowedTransitions: { value: string; label: string }[];
    can: { update: boolean; disburse: boolean };
}>();

const a = computed(() => props.application);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Finance', href: '/admin/finance' },
    { title: a.value.application_number, href: '#' },
];

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const transitionForm = useForm({
    status: '', lender_id: null as number | null, lender_application_number: '',
    sanction_amount: null as number | null, emi: null as number | null,
    interest_rate: null as number | null, rejection_reason: '', queries: '', remarks: '',
});
const needsSanction = computed(() => transitionForm.status === 'sanctioned');
const needsReason = computed(() => transitionForm.status === 'rejected');
const needsQuery = computed(() => transitionForm.status === 'query_raised');
function applyTransition() {
    if (!transitionForm.status) return;
    transitionForm.post(`/admin/finance/${a.value.id}/transition`, { preserveScroll: true, onSuccess: () => transitionForm.reset() });
}

const disburseForm = useForm({ amount: Number(a.value.sanction_amount ?? a.value.loan_amount ?? 0), utr: '' });
function disburse() {
    disburseForm.post(`/admin/finance/${a.value.id}/disburse`, { preserveScroll: true, onSuccess: () => disburseForm.reset() });
}

const canDisburse = computed(() => ['sanctioned', 'agreement_pending', 'disbursement_pending'].includes(a.value.status));
</script>

<template>
    <Head :title="application.application_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ application.application_number }}</h1>
                        <span class="rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-xs font-medium capitalize text-brand-maroon dark:text-brand-yellow">{{ (application.status ?? '').replace(/_/g, ' ') }}</span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ application.customer?.name }} · {{ application.customer?.mobile }} ·
                        <Link v-if="application.booking" :href="`/admin/bookings/${application.booking.id}`" class="underline">{{ application.booking.booking_number }}</Link>
                    </p>
                </div>
                <Button variant="outline" as-child><Link href="/admin/finance">Back</Link></Button>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Loan Amount</p><p class="text-lg font-bold">{{ money(application.loan_amount) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Sanction</p><p class="text-lg font-bold">{{ money(application.sanction_amount) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">EMI</p><p class="text-lg font-bold">{{ money(application.emi) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Disbursed</p><p class="text-lg font-bold text-emerald-600">{{ money(application.disbursed_amount) }}</p></CardContent></Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader><CardTitle class="text-base">Status History</CardTitle></CardHeader>
                        <CardContent>
                            <ol class="space-y-2 text-sm">
                                <li v-for="h in application.status_histories" :key="h.id" class="flex items-center justify-between border-b pb-2 last:border-0">
                                    <span class="capitalize">{{ (h.to_status ?? '').replace(/_/g, ' ') }}<span v-if="h.remarks" class="text-muted-foreground"> — {{ h.remarks }}</span></span>
                                    <span class="text-xs text-muted-foreground">{{ h.changer?.name ?? 'System' }} · {{ new Date(h.created_at).toLocaleDateString() }}</span>
                                </li>
                            </ol>
                        </CardContent>
                    </Card>

                    <Card v-if="application.disbursements?.length">
                        <CardHeader><CardTitle class="text-base">Disbursements</CardTitle></CardHeader>
                        <CardContent>
                            <ul class="divide-y text-sm">
                                <li v-for="d in application.disbursements" :key="d.id" class="flex items-center justify-between py-2">
                                    <span class="font-mono text-xs">{{ d.disbursement_number }}<span v-if="d.utr" class="text-muted-foreground"> · UTR {{ d.utr }}</span></span>
                                    <span class="font-medium">{{ money(d.amount) }}</span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-4">
                    <Card v-if="can.update && allowedTransitions.length">
                        <CardHeader><CardTitle class="text-base">Update Status</CardTitle></CardHeader>
                        <CardContent class="grid gap-2">
                            <select v-model="transitionForm.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="">Select status…</option>
                                <option v-for="t in allowedTransitions" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <select v-model="transitionForm.lender_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option :value="null">Lender (optional)</option>
                                <option v-for="l in lenders" :key="l.id" :value="l.id">{{ l.name }}</option>
                            </select>
                            <template v-if="needsSanction">
                                <Input v-model.number="transitionForm.sanction_amount" type="number" placeholder="Sanction amount" class="h-9" />
                                <Input v-model.number="transitionForm.emi" type="number" placeholder="EMI" class="h-9" />
                            </template>
                            <Input v-if="needsReason" v-model="transitionForm.rejection_reason" placeholder="Rejection reason" class="h-9" />
                            <Input v-if="needsQuery" v-model="transitionForm.queries" placeholder="Query details" class="h-9" />
                            <Button size="sm" :disabled="!transitionForm.status || transitionForm.processing" @click="applyTransition">Apply</Button>
                        </CardContent>
                    </Card>

                    <Card v-if="can.disburse && canDisburse">
                        <CardHeader><CardTitle class="text-base">Record Disbursement</CardTitle></CardHeader>
                        <CardContent class="grid gap-2">
                            <div class="grid gap-1"><Label class="text-xs">Amount</Label><Input v-model.number="disburseForm.amount" type="number" class="h-9" /></div>
                            <div class="grid gap-1"><Label class="text-xs">UTR / Reference</Label><Input v-model="disburseForm.utr" class="h-9" /></div>
                            <Button size="sm" :disabled="disburseForm.amount <= 0 || disburseForm.processing" @click="disburse">Disburse &amp; Credit Ledger</Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle class="text-base">Details</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-muted-foreground">Lender</span><span>{{ application.lender?.name ?? '—' }}</span>
                            <span class="text-muted-foreground">Lender App #</span><span>{{ application.lender_application_number ?? '—' }}</span>
                            <span class="text-muted-foreground">Interest</span><span>{{ application.interest_rate ? application.interest_rate + '%' : '—' }}</span>
                            <span class="text-muted-foreground">Tenure</span><span>{{ application.tenure_months ? application.tenure_months + ' mo' : '—' }}</span>
                            <span class="text-muted-foreground">Employer</span><span>{{ application.employer ?? '—' }}</span>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
