<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

interface StatusOption {
    value: string;
    label: string;
}

const props = defineProps<{
    lead: Record<string, any>;
    approvalRequest: Record<string, any> | null;
    statuses: StatusOption[];
    allowedTransitions: StatusOption[];
    employees: { id: number; name: string }[];
    can: Record<string, boolean>;
}>();

// The purchase-approval request lives on the lead (not on lead.purchase, which
// only exists once approved). Drive the whole Approval tab from this.
const approval_request = computed(() => props.approvalRequest);
const hasPendingApproval = computed(() => approval_request.value?.status === 'pending');
const pendingApproverName = computed<string>(() => {
    const step = approval_request.value?.steps?.find((s: Record<string, unknown>) => s.status === 'pending');
    return (step?.role as { name?: string } | undefined)?.name ?? 'the approver';
});
const canRequestApproval = computed(() => props.can.requestApproval && (!approval_request.value || approval_request.value.status === 'rejected'));

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Leads', href: '/admin/purchase-leads' },
    { title: props.lead.lead_number, href: '#' },
];

const tabs = ['Overview', 'Follow-ups', 'Documents', 'Valuation', 'Approval', 'Purchase'];
// Open straight to the Documents tab while the lead is awaiting document
// verification or seller KYC — that's the work due at these stages.
const documentStages = ['document_verification_pending', 'seller_kyc_pending'];
const activeTab = ref(documentStages.includes(props.lead.status) ? 'Documents' : 'Overview');

// Outstanding document/KYC work, surfaced as a badge on the Documents tab.
const pendingVerifications = computed(() => (props.lead.verifications ?? []).filter((v: Record<string, any>) => v.status === 'pending').length);
const documentsDue = computed(() => documentStages.includes(props.lead.status));
// Number of unverified items when we have them; otherwise a dot ('•') while the
// lead sits at a KYC/verification stage. Empty string = no badge.
const documentsBadge = computed<string>(() => {
    if (pendingVerifications.value > 0) return String(pendingVerifications.value);
    return documentsDue.value ? '•' : '';
});

function money(v: unknown): string {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}
function statusLabel(value: string): string {
    return props.statuses.find((s) => s.value === value)?.label ?? value;
}
function post(url: string, data: Record<string, string | number | boolean | null>, done?: () => void) {
    router.post(url, data, { preserveScroll: true, onSuccess: done });
}

// --- Status transition ---
const transition = useForm({ status: '', remarks: '', lost_reason: '' });
const lostStatuses = ['rejected', 'seller_not_interested', 'vehicle_sold_elsewhere'];
function applyTransition() {
    transition.post(`/admin/purchase-leads/${props.lead.id}/transition`, {
        preserveScroll: true,
        onSuccess: () => transition.reset(),
    });
}

// --- Assignment ---
const assignForm = useForm({ assigned_to: props.lead.assigned_to ?? null });
function applyAssign() {
    assignForm.post(`/admin/purchase-leads/${props.lead.id}/assign`, { preserveScroll: true });
}

// --- Follow-up ---
const followup = useForm({ contact_mode: 'call', outcome: '', remarks: '', next_follow_up_at: '' });
function addFollowup() {
    followup.post(`/admin/purchase-leads/${props.lead.id}/followup`, {
        preserveScroll: true,
        onSuccess: () => followup.reset(),
    });
}

// --- Document upload ---
const docForm = useForm<{ type: string; file: File | null }>({ type: 'aadhaar', file: null });
function uploadDoc() {
    docForm.post(`/admin/purchase-leads/${props.lead.id}/documents`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => docForm.reset(),
    });
}

// --- Verification ---
function setVerification(type: string, status: string) {
    post(`/admin/purchase-leads/${props.lead.id}/verification`, { type, status });
}

// --- Valuation ---
const v = props.lead.valuation ?? {};
const valuation = useForm({
    market_price: v.market_price ?? 0,
    expected_retail_price: v.expected_retail_price ?? 0,
    seller_expected_price: v.seller_expected_price ?? props.lead.expected_price ?? 0,
    repair_estimate: v.repair_estimate ?? 0,
    rto_expense: v.rto_expense ?? 0,
    documentation_expense: v.documentation_expense ?? 0,
    transportation_expense: v.transportation_expense ?? 0,
    insurance_expense: v.insurance_expense ?? 0,
    brokerage: v.brokerage ?? 0,
    holding_cost: v.holding_cost ?? 0,
    other_costs: v.other_costs ?? 0,
    target_profit: v.target_profit ?? 0,
    final_negotiated_price: v.final_negotiated_price ?? null,
    remarks: v.remarks ?? '',
});
const liveRecommended = computed(() => {
    const expenses =
        Number(valuation.repair_estimate) +
        Number(valuation.rto_expense) +
        Number(valuation.documentation_expense) +
        Number(valuation.transportation_expense) +
        Number(valuation.insurance_expense) +
        Number(valuation.brokerage) +
        Number(valuation.holding_cost) +
        Number(valuation.other_costs);
    return Number(valuation.expected_retail_price) - expenses - Number(valuation.target_profit);
});
function saveValuation() {
    valuation.post(`/admin/purchase-leads/${props.lead.id}/valuation`, { preserveScroll: true });
}

// --- Approval request ---
const approval = useForm({ requested_amount: v.final_negotiated_price ?? v.recommended_price ?? 0, reason: '' });
function requestApproval() {
    approval.post(`/admin/purchase-leads/${props.lead.id}/request-approval`, { preserveScroll: true });
}
function decide(approvalId: number, decision: 'approve' | 'reject') {
    const remarks = decision === 'reject' ? (prompt('Reason for rejection?') ?? '') : '';
    post(`/admin/approvals/${approvalId}/decide`, { decision, remarks });
}

// --- Purchase actions ---
const purchase = computed(() => props.lead.purchase);
function generateAgreement() {
    post(`/admin/purchases/${purchase.value.id}/agreement`, {});
}
const payment = useForm({ type: 'advance', amount: 0, method: 'neft', reference_number: '', recipient_type: 'seller', remarks: '' });
function recordPayment() {
    payment.post(`/admin/purchases/${purchase.value.id}/payments`, { preserveScroll: true, onSuccess: () => payment.reset() });
}
function approvePayment(id: number) {
    post(`/admin/seller-payments/${id}/approve`, {});
}
const possession = useForm({
    vehicle_received: true,
    original_rc_received: false,
    insurance_received: false,
    puc_received: false,
    noc_received: false,
    form_35_received: false,
    main_key: true,
    spare_key: false,
    service_book: false,
    tool_kit: false,
    spare_wheel: false,
    accessories: false,
    odometer_km: null as number | null,
    fuel_level: '',
    remarks: '',
});
function confirmPossession() {
    possession.post(`/admin/purchases/${purchase.value.id}/possession`, { preserveScroll: true });
}

const docTypes = ['aadhaar', 'pan', 'address_proof', 'photo', 'photo_with_vehicle', 'cancelled_cheque', 'signature', 'authorisation_letter', 'other'];
const verificationStatuses = ['pending', 'received', 'verified', 'rejected', 'expired', 'not_applicable'];
</script>

<template>
    <Head :title="lead.lead_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ lead.lead_number }}</h1>
                        <span
                            class="inline-flex rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-700 dark:bg-blue-900/40 dark:text-blue-400"
                        >
                            {{ statusLabel(lead.status) }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ lead.seller_name }} · {{ lead.mobile }} ·
                        {{ [lead.make, lead.model, lead.variant].filter(Boolean).join(' ') || 'Vehicle TBD' }}
                        {{ lead.registration_number ? '· ' + lead.registration_number : '' }}
                    </p>
                </div>
                <Button variant="outline" as-child><Link href="/admin/purchase-leads">Back to list</Link></Button>
            </div>

            <!-- Status transition bar -->
            <Card v-if="can.update && allowedTransitions.length">
                <CardContent class="flex flex-wrap items-end gap-3 py-4">
                    <div class="grid gap-1">
                        <Label class="text-xs">Move to</Label>
                        <select v-model="transition.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option value="">Select status…</option>
                            <option v-for="t in allowedTransitions" :key="t.value" :value="t.value">{{ t.label }}</option>
                        </select>
                    </div>
                    <div v-if="lostStatuses.includes(transition.status)" class="grid gap-1">
                        <Label class="text-xs">Lost reason *</Label>
                        <Input v-model="transition.lost_reason" class="h-9" placeholder="Reason…" />
                    </div>
                    <div class="grid flex-1 gap-1">
                        <Label class="text-xs">Remarks</Label>
                        <Input v-model="transition.remarks" class="h-9" placeholder="Optional note" />
                    </div>
                    <Button :disabled="!transition.status || transition.processing" @click="applyTransition">Apply</Button>
                </CardContent>
            </Card>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-1 border-b">
                <button
                    v-for="tab in tabs"
                    :key="tab"
                    class="flex items-center gap-1.5 border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === tab ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeTab = tab"
                >
                    {{ tab }}
                    <span
                        v-if="tab === 'Documents' && documentsBadge"
                        class="inline-flex min-w-[1.25rem] items-center justify-center rounded-full bg-brand-red px-1.5 text-xs font-semibold leading-5 text-white"
                        :title="
                            pendingVerifications > 0 ? `${pendingVerifications} document(s) pending verification` : 'Documents / seller KYC pending'
                        "
                        >{{ documentsBadge }}</span
                    >
                </button>
            </div>

            <!-- Overview -->
            <div v-show="activeTab === 'Overview'" class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Lead Details</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                        <span class="text-muted-foreground">Source</span><span class="capitalize">{{ lead.source }}</span>
                        <span class="text-muted-foreground">Priority</span><span class="capitalize">{{ lead.priority }}</span>
                        <span class="text-muted-foreground">Branch</span><span>{{ lead.branch?.name ?? '—' }}</span>
                        <span class="text-muted-foreground">Expected Price</span><span>{{ money(lead.expected_price) }}</span>
                        <span class="text-muted-foreground">Odometer</span><span>{{ lead.odometer_km ? lead.odometer_km + ' km' : '—' }}</span>
                        <span class="text-muted-foreground">Loan Status</span
                        ><span class="capitalize">{{ (lead.loan_status ?? '').replace('_', ' ') }}</span>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Assignment</CardTitle></CardHeader>
                    <CardContent class="grid gap-3">
                        <div class="flex items-end gap-2">
                            <div class="grid flex-1 gap-1">
                                <Label class="text-xs">Assigned To</Label>
                                <select
                                    v-model="assignForm.assigned_to"
                                    :disabled="!can.assign"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                >
                                    <option :value="null">Unassigned</option>
                                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                                </select>
                            </div>
                            <Button v-if="can.assign" variant="outline" :disabled="assignForm.processing" @click="applyAssign">Save</Button>
                        </div>
                        <div v-if="can.createInspection" class="border-t pt-3">
                            <Button variant="outline" size="sm" @click="post('/admin/inspections', { purchase_lead_id: lead.id })">
                                Schedule Inspection
                            </Button>
                        </div>
                    </CardContent>
                </Card>
                <Card class="lg:col-span-2">
                    <CardHeader><CardTitle>Status History</CardTitle></CardHeader>
                    <CardContent>
                        <ol class="space-y-2 text-sm">
                            <li v-for="h in lead.status_histories" :key="h.id" class="flex items-center justify-between border-b pb-2 last:border-0">
                                <span
                                    >{{ statusLabel(h.to_status)
                                    }}<span v-if="h.remarks" class="text-muted-foreground"> — {{ h.remarks }}</span></span
                                >
                                <span class="text-xs text-muted-foreground"
                                    >{{ h.changer?.name ?? 'System' }} · {{ new Date(h.created_at).toLocaleString() }}</span
                                >
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>

            <!-- Follow-ups -->
            <div v-show="activeTab === 'Follow-ups'" class="grid gap-4">
                <Card v-if="can.update">
                    <CardHeader><CardTitle>Add Follow-up</CardTitle></CardHeader>
                    <CardContent class="flex flex-wrap items-end gap-3">
                        <div class="grid gap-1">
                            <Label class="text-xs">Mode</Label>
                            <select v-model="followup.contact_mode" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="call">Call</option>
                                <option value="whatsapp">WhatsApp</option>
                                <option value="visit">Visit</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="grid flex-1 gap-1">
                            <Label class="text-xs">Remarks</Label>
                            <Input v-model="followup.remarks" class="h-9" />
                        </div>
                        <div class="grid gap-1">
                            <Label class="text-xs">Next Follow-up</Label>
                            <Input v-model="followup.next_follow_up_at" type="datetime-local" class="h-9" />
                        </div>
                        <Button :disabled="followup.processing" @click="addFollowup">Add</Button>
                    </CardContent>
                </Card>
                <Card>
                    <CardContent class="py-4">
                        <p v-if="!lead.followups?.length" class="py-6 text-center text-sm text-muted-foreground">No follow-ups yet.</p>
                        <ol v-else class="space-y-3 text-sm">
                            <li v-for="f in lead.followups" :key="f.id" class="border-b pb-2 last:border-0">
                                <div class="flex justify-between">
                                    <span class="font-medium capitalize">{{ f.contact_mode }}</span>
                                    <span class="text-xs text-muted-foreground"
                                        >{{ f.user?.name }} · {{ new Date(f.created_at).toLocaleString() }}</span
                                    >
                                </div>
                                <p v-if="f.remarks" class="text-muted-foreground">{{ f.remarks }}</p>
                                <p v-if="f.next_follow_up_at" class="text-xs text-blue-600 dark:text-blue-400">
                                    Next: {{ new Date(f.next_follow_up_at).toLocaleString() }}
                                </p>
                            </li>
                        </ol>
                    </CardContent>
                </Card>
            </div>

            <!-- Documents & Verification -->
            <div v-show="activeTab === 'Documents'" class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Seller KYC Documents</CardTitle></CardHeader>
                    <CardContent class="grid gap-3">
                        <div v-if="can.update" class="flex items-end gap-2">
                            <div class="grid gap-1">
                                <Label class="text-xs">Type</Label>
                                <select v-model="docForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option v-for="t in docTypes" :key="t" :value="t">{{ t.replace(/_/g, ' ') }}</option>
                                </select>
                            </div>
                            <input
                                type="file"
                                class="text-sm"
                                accept=".jpg,.jpeg,.png,.pdf"
                                @input="docForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                            />
                            <Button size="sm" :disabled="!docForm.file || docForm.processing" @click="uploadDoc">Upload</Button>
                        </div>
                        <InputError v-if="docForm.errors.file" :message="docForm.errors.file" />
                        <p v-if="!lead.documents?.length" class="py-4 text-center text-sm text-muted-foreground">No documents uploaded.</p>
                        <ul v-else class="divide-y text-sm">
                            <li v-for="d in lead.documents" :key="d.id" class="flex items-center justify-between py-2">
                                <span class="capitalize">{{ d.type.replace(/_/g, ' ') }}</span>
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{ d.status }}</span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Vehicle Document Verification</CardTitle></CardHeader>
                    <CardContent>
                        <ul class="divide-y text-sm">
                            <li v-for="ver in lead.verifications" :key="ver.id" class="flex items-center justify-between gap-2 py-2">
                                <span class="capitalize">{{ ver.type.replace(/_/g, ' ') }}</span>
                                <select
                                    :value="ver.status"
                                    :disabled="!can.update"
                                    class="h-8 rounded-md border border-input bg-transparent px-2 text-xs shadow-sm"
                                    @change="setVerification(ver.type, ($event.target as HTMLSelectElement).value)"
                                >
                                    <option v-for="s in verificationStatuses" :key="s" :value="s">{{ s.replace('_', ' ') }}</option>
                                </select>
                            </li>
                        </ul>
                    </CardContent>
                </Card>
            </div>

            <!-- Valuation -->
            <div v-show="activeTab === 'Valuation'" class="grid gap-4">
                <Card v-if="!can.viewCost && !lead.valuation">
                    <CardContent class="py-8 text-center text-sm text-muted-foreground"
                        >You do not have permission to view valuation costs.</CardContent
                    >
                </Card>
                <Card v-else>
                    <CardHeader><CardTitle>Vehicle Valuation</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid grid-cols-2 gap-4 md:grid-cols-4">
                            <div
                                v-for="field in [
                                    ['expected_retail_price', 'Expected Retail'],
                                    ['seller_expected_price', 'Seller Expected'],
                                    ['repair_estimate', 'Repair Est.'],
                                    ['rto_expense', 'RTO'],
                                    ['documentation_expense', 'Documentation'],
                                    ['transportation_expense', 'Transport'],
                                    ['insurance_expense', 'Insurance'],
                                    ['brokerage', 'Brokerage'],
                                    ['holding_cost', 'Holding'],
                                    ['other_costs', 'Other'],
                                    ['target_profit', 'Target Profit'],
                                    ['final_negotiated_price', 'Negotiated Price'],
                                ]"
                                :key="field[0]"
                                class="grid gap-1"
                            >
                                <Label class="text-xs">{{ field[1] }}</Label>
                                <Input
                                    v-model.number="(valuation as any)[field[0]]"
                                    type="number"
                                    min="0"
                                    class="h-9"
                                    :disabled="can.saveValuation === false"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg bg-muted/50 p-4">
                            <div>
                                <p class="text-xs text-muted-foreground">Recommended Max Purchase Price</p>
                                <p class="text-2xl font-bold">{{ money(liveRecommended) }}</p>
                            </div>
                            <div v-if="lead.valuation" class="text-sm">
                                <p>
                                    Expected Net Profit: <strong>{{ money(lead.valuation.expected_net_profit) }}</strong>
                                </p>
                                <p>
                                    Expected Margin: <strong>{{ lead.valuation.expected_margin_pct }}%</strong>
                                </p>
                            </div>
                            <Button v-if="can.saveValuation" :disabled="valuation.processing" @click="saveValuation">Save Valuation</Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Approval -->
            <div v-show="activeTab === 'Approval'" class="grid gap-4">
                <Card v-if="canRequestApproval">
                    <CardHeader><CardTitle>Request Purchase Approval</CardTitle></CardHeader>
                    <CardContent class="flex flex-wrap items-end gap-3">
                        <div class="grid gap-1">
                            <Label class="text-xs">Requested Amount (₹)</Label>
                            <Input v-model.number="approval.requested_amount" type="number" min="0" class="h-9" />
                        </div>
                        <div class="grid flex-1 gap-1">
                            <Label class="text-xs">Reason</Label>
                            <Input v-model="approval.reason" class="h-9" />
                        </div>
                        <Button :disabled="approval.processing" @click="requestApproval">Request Approval</Button>
                    </CardContent>
                </Card>

                <Card v-if="approval_request">
                    <CardHeader
                        ><CardTitle>Approval {{ approval_request.approval_number }}</CardTitle></CardHeader
                    >
                    <CardContent class="grid gap-3 text-sm">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{ approval_request.status }}</span>
                            <span>Requested: {{ money(approval_request.requested_amount) }}</span>
                            <span v-if="approval_request.approved_amount">· Approved: {{ money(approval_request.approved_amount) }}</span>
                        </div>
                        <div v-if="approval_request.reasons?.length" class="flex flex-wrap gap-1">
                            <span
                                v-for="r in approval_request.reasons"
                                :key="r"
                                class="rounded-full bg-amber-100 px-2 py-0.5 text-xs text-amber-700 dark:bg-amber-900/40 dark:text-amber-400"
                            >
                                {{ r.replace(/_/g, ' ') }}
                            </span>
                        </div>
                        <ol class="divide-y">
                            <li v-for="step in approval_request.steps" :key="step.id" class="flex items-center justify-between py-2">
                                <span>Step {{ step.sequence }}: {{ step.role?.name ?? 'Manager' }}</span>
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{ step.status }}</span>
                            </li>
                        </ol>
                        <div v-if="can.decideApproval && hasPendingApproval" class="flex gap-2">
                            <Button size="sm" @click="decide(approval_request.id, 'approve')">Approve</Button>
                            <Button size="sm" variant="destructive" @click="decide(approval_request.id, 'reject')">Reject</Button>
                        </div>
                        <p v-else-if="hasPendingApproval" class="text-xs text-muted-foreground">Awaiting decision by {{ pendingApproverName }}.</p>
                    </CardContent>
                </Card>
                <Card v-else-if="!can.requestApproval">
                    <CardContent class="py-8 text-center text-sm text-muted-foreground">No approval raised yet.</CardContent>
                </Card>
            </div>

            <!-- Purchase -->
            <div v-show="activeTab === 'Purchase'" class="grid gap-4">
                <Card v-if="!lead.purchase">
                    <CardContent class="py-8 text-center text-sm text-muted-foreground">
                        The purchase record is created automatically once the purchase approval is granted.
                    </CardContent>
                </Card>
                <template v-else>
                    <Card>
                        <CardHeader
                            ><CardTitle>Purchase {{ lead.purchase.purchase_number }}</CardTitle></CardHeader
                        >
                        <CardContent class="grid gap-3 text-sm">
                            <div class="flex flex-wrap items-center gap-4">
                                <span
                                    >Agreed Price: <strong>{{ money(lead.purchase.agreed_price) }}</strong></span
                                >
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{
                                    (lead.purchase.status ?? '').replace(/_/g, ' ')
                                }}</span>
                            </div>
                            <div v-if="can.generateAgreement && !lead.purchase.agreement_document_id">
                                <Button size="sm" @click="generateAgreement">Generate Purchase Agreement</Button>
                            </div>
                            <div v-else-if="lead.purchase.agreement_document_id">
                                <a
                                    :href="`/admin/documents/${lead.purchase.agreement_document_id}/download`"
                                    class="text-sm text-blue-600 underline dark:text-blue-400"
                                    >Download Agreement PDF</a
                                >
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle>Seller Payments</CardTitle></CardHeader>
                        <CardContent class="grid gap-3">
                            <div v-if="can.recordPayment" class="flex flex-wrap items-end gap-2">
                                <div class="grid gap-1">
                                    <Label class="text-xs">Type</Label>
                                    <select v-model="payment.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                        <option value="token">Token</option>
                                        <option value="advance">Advance</option>
                                        <option value="full">Full</option>
                                        <option value="balance">Balance</option>
                                    </select>
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Amount</Label
                                    ><Input v-model.number="payment.amount" type="number" min="0" class="h-9 w-32" />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Reference</Label><Input v-model="payment.reference_number" class="h-9" />
                                </div>
                                <Button size="sm" :disabled="payment.processing || payment.amount <= 0" @click="recordPayment">Record</Button>
                            </div>
                            <p v-if="!lead.purchase.payments?.length" class="py-2 text-center text-sm text-muted-foreground">No payments recorded.</p>
                            <ul v-else class="divide-y text-sm">
                                <li v-for="p in lead.purchase.payments" :key="p.id" class="flex items-center justify-between py-2">
                                    <span class="capitalize">{{ p.type }} · {{ money(p.amount) }}</span>
                                    <span class="flex items-center gap-2">
                                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{
                                            (p.status ?? '').replace('_', ' ')
                                        }}</span>
                                        <Button
                                            v-if="can.approvePayment && p.status === 'pending_approval'"
                                            size="sm"
                                            variant="outline"
                                            @click="approvePayment(p.id)"
                                            >Approve</Button
                                        >
                                    </span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>

                    <Card v-if="can.confirmPossession && !lead.purchase.possession">
                        <CardHeader><CardTitle>Confirm Possession</CardTitle></CardHeader>
                        <CardContent class="grid gap-3">
                            <p class="text-xs text-muted-foreground">Confirming possession creates the stock entry automatically.</p>
                            <div class="grid grid-cols-2 gap-2 md:grid-cols-4">
                                <label
                                    v-for="item in [
                                        ['vehicle_received', 'Vehicle received'],
                                        ['original_rc_received', 'Original RC'],
                                        ['insurance_received', 'Insurance'],
                                        ['puc_received', 'PUC'],
                                        ['noc_received', 'NOC'],
                                        ['form_35_received', 'Form 35'],
                                        ['main_key', 'Main key'],
                                        ['spare_key', 'Spare key'],
                                        ['service_book', 'Service book'],
                                        ['tool_kit', 'Tool kit'],
                                        ['spare_wheel', 'Spare wheel'],
                                        ['accessories', 'Accessories'],
                                    ]"
                                    :key="item[0]"
                                    class="flex items-center gap-2 text-sm"
                                >
                                    <input type="checkbox" v-model="(possession as any)[item[0]]" />{{ item[1] }}
                                </label>
                            </div>
                            <div class="flex items-end gap-2">
                                <div class="grid gap-1">
                                    <Label class="text-xs">Odometer</Label
                                    ><Input v-model.number="possession.odometer_km" type="number" class="h-9 w-32" />
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Fuel</Label><Input v-model="possession.fuel_level" class="h-9 w-24" />
                                </div>
                                <Button :disabled="!possession.vehicle_received || possession.processing" @click="confirmPossession"
                                    >Confirm Possession &amp; Create Stock</Button
                                >
                            </div>
                        </CardContent>
                    </Card>
                    <Card v-else-if="lead.purchase.possession">
                        <CardContent class="py-4 text-sm">
                            <p class="font-medium text-green-700 dark:text-green-400">Possession confirmed.</p>
                            <p class="text-muted-foreground">
                                Vehicle possessed on {{ new Date(lead.purchase.possession.possessed_at).toLocaleString() }}.
                            </p>
                        </CardContent>
                    </Card>
                </template>
            </div>
        </div>
    </AppLayout>
</template>
