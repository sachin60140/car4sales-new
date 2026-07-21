<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

const props = defineProps<{
    submission: Record<string, any>;
    docStatuses: string[];
    can: { review: boolean; verifyDocs: boolean; issueAgreement: boolean; recordPayment: boolean; confirmPossession: boolean };
}>();

const s = computed(() => props.submission);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendor Submissions', href: '/admin/vendor-submissions' },
    { title: s.value.submission_number, href: '#' },
];

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const approveForm = useForm({ remarks: '' });
const rejectForm = useForm({ remarks: '' });
function approve() {
    approveForm.post(`/admin/vendor-submissions/${s.value.id}/approve`, { preserveScroll: true });
}
function reject() {
    if (!rejectForm.remarks) return;
    rejectForm.post(`/admin/vendor-submissions/${s.value.id}/reject`, { preserveScroll: true });
}

// --- Settlement ---
const settlement = computed<string>(() => s.value.settlement_status);
const payForm = useForm<{ payment_amount: number | null; payment_mode: string; payment_reference: string; payment_date: string; proof: File | null }>(
    {
        payment_amount: Number(s.value.expected_amount ?? 0),
        payment_mode: 'neft',
        payment_reference: '',
        payment_date: new Date().toISOString().slice(0, 10),
        proof: null,
    },
);
function onProof(e: Event) {
    payForm.proof = (e.target as HTMLInputElement).files?.[0] ?? null;
}
function recordPayment() {
    payForm.post(`/admin/vendor-submissions/${s.value.id}/record-payment`, { preserveScroll: true, forceFormData: true });
}
const paymentModes = ['neft', 'rtgs', 'upi', 'cheque', 'cash'];

// --- Owner-document verification (per-document table) ---
const rows = ref<any[]>([]);
watch(
    () => s.value.verification?.rows,
    (r) => {
        rows.value = (r ?? []).map((x: any) => ({ ...x }));
    },
    { immediate: true, deep: true },
);
const verificationExtras = computed<any[]>(() => s.value.verification?.extras ?? []);
const savingRow = ref<string | null>(null);
function saveRow(row: any) {
    savingRow.value = row.key;
    router.post(
        `/admin/vendor-submissions/${s.value.id}/verify-document`,
        { type: row.key, status: row.status, number: row.number, valid_till: row.valid_till, remarks: row.remarks },
        { preserveScroll: true, onFinish: () => (savingRow.value = null) },
    );
}
const docStatusStyle: Record<string, string> = {
    pending: 'text-brand-orange',
    verified: 'text-emerald-600',
    rejected: 'text-brand-red',
    not_applicable: 'text-muted-foreground',
};

// Issue agreement / send back.
const kycApproveForm = useForm({ remarks: '' });
const kycRejectForm = useForm({ remarks: '' });
function issueAgreement() {
    kycApproveForm.post(`/admin/vendor-submissions/${s.value.id}/approve-kyc`, { preserveScroll: true });
}
function sendBack() {
    if (!kycRejectForm.remarks) return;
    kycRejectForm.post(`/admin/vendor-submissions/${s.value.id}/reject-kyc`, { preserveScroll: true });
}

// --- Confirm possession → create stock ---
const possessionChecks = [
    { key: 'vehicle_received', label: 'Vehicle received' },
    { key: 'original_rc_received', label: 'Original RC' },
    { key: 'insurance_received', label: 'Insurance' },
    { key: 'puc_received', label: 'PUC' },
    { key: 'noc_received', label: 'NOC' },
    { key: 'form_35_received', label: 'Form 35' },
    { key: 'main_key', label: 'Main key' },
    { key: 'spare_key', label: 'Spare key' },
    { key: 'service_book', label: 'Service book' },
    { key: 'tool_kit', label: 'Tool kit' },
    { key: 'spare_wheel', label: 'Spare wheel' },
    { key: 'accessories', label: 'Accessories' },
];
const possessionForm = useForm<Record<string, any>>({
    vehicle_received: true,
    original_rc_received: false,
    insurance_received: false,
    puc_received: false,
    noc_received: false,
    form_35_received: false,
    main_key: false,
    spare_key: false,
    service_book: false,
    tool_kit: false,
    spare_wheel: false,
    accessories: false,
    odometer_km: s.value.odometer_km ?? null,
    fuel_level: '',
    remarks: '',
});
function confirmPossession() {
    possessionForm.post(`/admin/vendor-submissions/${s.value.id}/confirm-possession`, { preserveScroll: true });
}

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    pending_review: 'bg-brand-orange/15 text-brand-orange',
    approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
};
const resultStyle: Record<string, string> = { pass: 'text-emerald-600', fail: 'text-brand-red', na: 'text-muted-foreground' };
</script>

<template>
    <Head :title="s.submission_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-xl font-semibold">{{ s.make }} {{ s.model }} {{ s.variant }}</h1>
                        <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[s.status]">{{ s.status_label }}</span>
                    </div>
                    <p class="text-sm text-muted-foreground">{{ s.submission_number }} · {{ s.vendor.company ?? s.vendor.name }}</p>
                </div>
                <Button variant="outline" as-child><Link href="/admin/vendor-submissions">Back</Link></Button>
            </div>

            <div v-if="s.purchase_lead" class="rounded-lg border border-emerald-500/40 bg-emerald-500/5 px-4 py-3 text-sm">
                Approved → purchase lead
                <Link :href="`/admin/purchase-leads/${s.purchase_lead.id}`" class="font-medium underline">{{ s.purchase_lead.lead_number }}</Link
                >.
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <Card>
                        <CardHeader><CardTitle class="text-base">Vehicle</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-y-2 text-sm sm:grid-cols-3">
                            <span class="text-muted-foreground">Year</span><span class="sm:col-span-2">{{ s.manufacturing_year ?? '—' }}</span>
                            <span class="text-muted-foreground">Reg. No.</span
                            ><span class="sm:col-span-2"
                                >{{ s.registration_number ?? '—' }} <span v-if="s.registration_state">({{ s.registration_state }})</span></span
                            >
                            <span class="text-muted-foreground">Fuel / Trans.</span
                            ><span class="sm:col-span-2">{{ s.fuel_type ?? '—' }} / {{ s.transmission ?? '—' }}</span>
                            <span class="text-muted-foreground">Odometer</span
                            ><span class="sm:col-span-2">{{ s.odometer_km ? Number(s.odometer_km).toLocaleString('en-IN') + ' km' : '—' }}</span>
                            <span class="text-muted-foreground">Colour</span><span class="sm:col-span-2">{{ s.color ?? '—' }}</span>
                            <span class="text-muted-foreground">Owner No.</span><span class="sm:col-span-2">{{ s.ownership_serial ?? '—' }}</span>
                        </CardContent>
                    </Card>

                    <Card v-if="s.items?.length">
                        <CardHeader><CardTitle class="text-base">Condition Report</CardTitle></CardHeader>
                        <CardContent>
                            <ul class="divide-y text-sm">
                                <li v-for="it in s.items" :key="it.id" class="flex items-center justify-between gap-2 py-2">
                                    <span
                                        >{{ it.label }} <span class="text-xs text-muted-foreground">· {{ it.section }}</span
                                        ><span v-if="it.remarks" class="text-xs text-muted-foreground"> — {{ it.remarks }}</span></span
                                    >
                                    <span class="flex items-center gap-2">
                                        <span v-if="it.rating" class="text-xs text-muted-foreground">{{ it.rating }}★</span>
                                        <span class="font-medium uppercase" :class="resultStyle[it.result]">{{
                                            it.result === 'na' ? 'N/A' : it.result
                                        }}</span>
                                    </span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <Card v-if="s.gallery?.length">
                        <CardHeader><CardTitle class="text-base">Photos</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <a v-for="m in s.gallery" :key="m.id" :href="m.url" target="_blank" class="overflow-hidden rounded-lg border">
                                <img :src="m.url" alt="" class="aspect-video w-full object-cover" />
                            </a>
                        </CardContent>
                    </Card>

                    <Card v-if="s.damage?.length">
                        <CardHeader><CardTitle class="text-base">Damaged Parts</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <a v-for="m in s.damage" :key="m.id" :href="m.url" target="_blank" class="overflow-hidden rounded-lg border">
                                <img :src="m.url" alt="" class="aspect-video w-full object-cover" />
                            </a>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-4">
                    <Card>
                        <CardContent class="p-4">
                            <p class="text-xs text-muted-foreground">Vendor Expected Amount</p>
                            <p class="text-2xl font-bold">{{ money(s.expected_amount) }}</p>
                            <p v-if="s.overall_rating" class="mt-1 text-sm text-muted-foreground">Overall: {{ s.overall_rating }}★</p>
                            <p v-if="s.overall_remark" class="mt-2 text-sm">{{ s.overall_remark }}</p>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle class="text-base">Vendor</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-muted-foreground">Name</span><span>{{ s.vendor.name }}</span>
                            <span class="text-muted-foreground">Company</span><span>{{ s.vendor.company ?? '—' }}</span>
                            <span class="text-muted-foreground">Phone</span><span>{{ s.vendor.phone ?? '—' }}</span>
                            <span class="text-muted-foreground">Email</span><span class="truncate">{{ s.vendor.email ?? '—' }}</span>
                        </CardContent>
                    </Card>

                    <Card v-if="can.review">
                        <CardHeader><CardTitle class="text-base">Review</CardTitle></CardHeader>
                        <CardContent class="grid gap-3">
                            <div class="grid gap-1.5">
                                <Label class="text-xs">Approve — optional note</Label>
                                <Input v-model="approveForm.remarks" placeholder="Note (optional)" class="h-9" />
                                <Button size="sm" :disabled="approveForm.processing" @click="approve">Approve → create lead</Button>
                            </div>
                            <div class="grid gap-1.5 border-t pt-3">
                                <Label class="text-xs">Reject — reason required</Label>
                                <Input v-model="rejectForm.remarks" placeholder="Reason for rejection" class="h-9" />
                                <Button size="sm" variant="destructive" :disabled="!rejectForm.remarks || rejectForm.processing" @click="reject"
                                    >Reject</Button
                                >
                            </div>
                        </CardContent>
                    </Card>
                    <Card v-else-if="s.review_remarks">
                        <CardHeader><CardTitle class="text-base">Review Notes</CardTitle></CardHeader>
                        <CardContent class="text-sm text-muted-foreground">{{ s.review_remarks }}</CardContent>
                    </Card>

                    <!-- Settlement -->
                    <Card v-if="s.status === 'approved'">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <CardTitle class="text-base">Settlement</CardTitle>
                            <Button v-if="s.agreement_url" size="sm" variant="outline" as-child><a :href="s.agreement_url">Agreement</a></Button>
                        </CardHeader>
                        <CardContent class="grid gap-3 text-sm">
                            <span class="w-fit rounded-full bg-muted px-2 py-0.5 text-xs">{{ s.settlement_label }}</span>

                            <p v-if="settlement === 'kyc_pending'" class="text-xs text-muted-foreground">
                                Waiting for the vendor to submit owner details &amp; documents.
                            </p>

                            <!-- Owner (seller) + vehicle identity -->
                            <div v-if="s.owner" class="rounded-lg border border-sidebar-border/60 p-2">
                                <p class="text-xs font-medium uppercase text-muted-foreground">Owner (Seller)</p>
                                <p>{{ s.owner.name }}</p>
                                <p class="text-muted-foreground">
                                    {{ s.owner.phone }}<span v-if="s.owner.email"> · {{ s.owner.email }}</span>
                                </p>
                                <p class="text-muted-foreground">{{ s.owner.address }}</p>
                                <p v-if="s.owner.pan" class="text-muted-foreground">PAN {{ s.owner.pan }}</p>
                                <p class="mt-1 border-t pt-1 text-muted-foreground">
                                    <span v-if="s.chassis_number">Chassis {{ s.chassis_number }} · </span>
                                    <span v-if="s.keys_available">Keys: {{ s.keys_available }} · </span>
                                    <span :class="s.has_hypothecation ? 'text-brand-orange' : ''">{{
                                        s.has_hypothecation ? 'Under hypothecation' : 'No hypothecation'
                                    }}</span>
                                </p>
                            </div>

                            <!-- Owner payout bank -->
                            <div v-if="s.bank" class="rounded-lg border border-sidebar-border/60 p-2">
                                <p class="text-xs font-medium uppercase text-muted-foreground">Owner's bank account (payout)</p>
                                <p>{{ s.bank.account_name }}</p>
                                <p class="text-muted-foreground">
                                    A/c {{ s.bank.account_number }} · {{ s.bank.ifsc }}<span v-if="s.bank.bank_name"> · {{ s.bank.bank_name }}</span>
                                </p>
                            </div>

                            <!-- Document verification table -->
                            <div v-if="rows.length" class="rounded-lg border border-sidebar-border/60">
                                <div class="border-b px-2 py-1.5 text-xs font-medium uppercase text-muted-foreground">Document verification</div>
                                <div class="divide-y">
                                    <div v-for="row in rows" :key="row.key" class="grid gap-2 p-2 sm:grid-cols-12 sm:items-start">
                                        <div class="sm:col-span-4">
                                            <p class="text-sm font-medium">
                                                {{ row.label }} <span v-if="row.group !== 'optional'" class="text-brand-red">*</span>
                                            </p>
                                            <div class="mt-0.5 flex flex-wrap gap-2">
                                                <a
                                                    v-for="(f, i) in row.files"
                                                    :key="i"
                                                    :href="f.url"
                                                    target="_blank"
                                                    class="text-xs text-muted-foreground underline"
                                                    >{{ f.side || 'View' }}</a
                                                >
                                                <span v-if="!row.files.length" class="text-xs text-brand-red">Not uploaded</span>
                                            </div>
                                        </div>
                                        <div class="sm:col-span-8">
                                            <div v-if="can.verifyDocs" class="grid gap-1.5 sm:grid-cols-3">
                                                <select
                                                    v-model="row.status"
                                                    class="h-8 rounded-md border border-input bg-transparent px-2 text-xs shadow-sm"
                                                >
                                                    <option v-for="st in docStatuses" :key="st" :value="st">{{ st.replace('_', ' ') }}</option>
                                                </select>
                                                <Input v-model="row.number" placeholder="Number" class="h-8 text-xs" />
                                                <Input v-model="row.valid_till" type="date" class="h-8 text-xs" />
                                                <Input v-model="row.remarks" placeholder="Remarks" class="h-8 text-xs sm:col-span-2" />
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    class="h-8"
                                                    :disabled="savingRow === row.key"
                                                    @click="saveRow(row)"
                                                    >Save</Button
                                                >
                                            </div>
                                            <div v-else class="text-xs">
                                                <span class="font-medium capitalize" :class="docStatusStyle[row.status]">{{
                                                    (row.status || 'pending').replace('_', ' ')
                                                }}</span>
                                                <span v-if="row.number" class="text-muted-foreground"> · {{ row.number }}</span>
                                                <span v-if="row.remarks" class="text-muted-foreground"> · {{ row.remarks }}</span>
                                            </div>
                                            <p v-if="row.verified_by" class="mt-1 text-[11px] text-muted-foreground">
                                                Verified by {{ row.verified_by }}<span v-if="row.verified_at"> · {{ row.verified_at }}</span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="verificationExtras.length" class="flex flex-wrap gap-1.5 border-t px-2 py-1.5">
                                    <span class="text-xs text-muted-foreground">Other:</span>
                                    <a v-for="(x, i) in verificationExtras" :key="i" :href="x.url" target="_blank" class="text-xs underline"
                                        >Doc {{ i + 1 }}</a
                                    >
                                </div>
                            </div>

                            <!-- Issue agreement / send back -->
                            <div v-if="can.verifyDocs" class="grid gap-2 border-t pt-3">
                                <Input v-model="kycApproveForm.remarks" placeholder="Note (optional)" class="h-9" />
                                <Button size="sm" :disabled="!can.issueAgreement || kycApproveForm.processing" @click="issueAgreement"
                                    >Issue Agreement</Button
                                >
                                <p v-if="!can.issueAgreement" class="text-xs text-muted-foreground">
                                    Mark every required document “verified” to issue the agreement.
                                </p>
                                <div class="border-t pt-2">
                                    <Input v-model="kycRejectForm.remarks" placeholder="Reason to send back" class="h-9" />
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        class="mt-2"
                                        :disabled="!kycRejectForm.remarks || kycRejectForm.processing"
                                        @click="sendBack"
                                        >Send back to vendor</Button
                                    >
                                </div>
                            </div>
                            <p
                                v-else-if="s.kyc_remarks && settlement === 'kyc_pending'"
                                class="rounded-lg border border-brand-red/40 bg-brand-red/5 p-2 text-xs text-brand-red"
                            >
                                Sent back: {{ s.kyc_remarks }}
                            </p>

                            <!-- Record payment -->
                            <div v-if="can.recordPayment" class="grid gap-2 border-t pt-3">
                                <div class="grid gap-1">
                                    <Label class="text-xs">Amount paid (₹)</Label
                                    ><Input v-model.number="payForm.payment_amount" type="number" class="h-9" />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Mode</Label>
                                    <select
                                        v-model="payForm.payment_mode"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                    >
                                        <option v-for="m in paymentModes" :key="m" :value="m" class="uppercase">{{ m.toUpperCase() }}</option>
                                    </select>
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Reference / UTR</Label><Input v-model="payForm.payment_reference" class="h-9" />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Date</Label><Input v-model="payForm.payment_date" type="date" class="h-9" />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Payment Screenshot</Label
                                    ><input type="file" accept="image/*" class="text-xs" @change="onProof" />
                                </div>
                                <Button size="sm" :disabled="!payForm.payment_amount || payForm.processing" @click="recordPayment"
                                    >Record Payment</Button
                                >
                            </div>

                            <div v-else-if="s.payment" class="rounded-lg border border-emerald-500/40 bg-emerald-500/5 p-2">
                                <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-400">Paid</p>
                                <p class="text-lg font-bold">{{ money(s.payment.amount) }}</p>
                                <p class="capitalize text-muted-foreground">
                                    {{ s.payment.mode }}<span v-if="s.payment.reference"> · {{ s.payment.reference }}</span
                                    ><span v-if="s.payment.date"> · {{ s.payment.date }}</span>
                                </p>
                                <a v-if="s.payment_proof" :href="s.payment_proof.url" target="_blank" class="text-xs underline">Payment proof</a>
                            </div>

                            <p v-else-if="settlement === 'agreement_ready'" class="text-xs text-muted-foreground">
                                Documents verified. Waiting for the vendor to request payment.
                            </p>

                            <!-- Confirm possession → create stock -->
                            <div v-if="can.confirmPossession" class="grid gap-2 border-t pt-3">
                                <Label class="text-xs font-semibold">Confirm Possession &amp; Create Stock</Label>
                                <p class="text-xs text-muted-foreground">Confirming possession creates the inventory entry automatically.</p>
                                <div class="grid grid-cols-2 gap-1.5">
                                    <label v-for="c in possessionChecks" :key="c.key" class="flex items-center gap-2 text-xs">
                                        <input v-model="possessionForm[c.key]" type="checkbox" class="size-3.5" /> {{ c.label }}
                                    </label>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="grid gap-1">
                                        <Label class="text-xs">Odometer</Label
                                        ><Input v-model.number="possessionForm.odometer_km" type="number" class="h-9" />
                                    </div>
                                    <div class="grid gap-1">
                                        <Label class="text-xs">Fuel</Label><Input v-model="possessionForm.fuel_level" class="h-9" />
                                    </div>
                                </div>
                                <Button size="sm" :disabled="!possessionForm.vehicle_received || possessionForm.processing" @click="confirmPossession"
                                    >Confirm Possession &amp; Create Stock</Button
                                >
                                <p v-if="!possessionForm.vehicle_received" class="text-xs text-muted-foreground">
                                    Tick “Vehicle received” to create the stock entry.
                                </p>
                            </div>

                            <!-- Stocked -->
                            <div v-if="s.vehicle" class="rounded-lg border border-emerald-500/40 bg-emerald-500/5 p-2 text-sm">
                                <p class="text-xs font-medium uppercase text-emerald-700 dark:text-emerald-400">In inventory</p>
                                <Link :href="`/admin/inventory/${s.vehicle.id}`" class="font-medium underline">{{ s.vehicle.stock_number }}</Link>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
