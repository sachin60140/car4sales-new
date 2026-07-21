<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendorLayout from '@/layouts/VendorLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, FileCheck2, Upload } from 'lucide-vue-next';
import { computed } from 'vue';

interface Doc {
    key: string;
    label: string;
    group: string;
    sides: number;
}

const props = defineProps<{
    submission: Record<string, any>;
    docCatalog: Doc[];
}>();
const s = computed(() => props.submission);

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

// --- Settlement (post-approval) ---
const settlement = computed<string>(() => s.value.settlement_status);

// Media types a document maps to (front/back for two-sided docs).
function mediaTypes(d: Doc): string[] {
    return d.sides === 2 ? [`${d.key}_front`, `${d.key}_back`] : [d.key];
}
const allTypes = props.docCatalog.flatMap(mediaTypes);

// Owner KYC: owner + vehicle-identity + bank details + catalog documents.
const kycForm = useForm<{
    owner_name: string;
    owner_phone: string;
    owner_email: string;
    owner_address: string;
    owner_pan: string;
    chassis_number: string;
    has_hypothecation: boolean;
    bank_account_name: string;
    bank_account_number: string;
    bank_ifsc: string;
    bank_name: string;
    documents: Record<string, File | null>;
    extra_documents: File[];
}>({
    owner_name: '',
    owner_phone: '',
    owner_email: '',
    owner_address: '',
    owner_pan: '',
    chassis_number: '',
    has_hypothecation: false,
    bank_account_name: '',
    bank_account_number: '',
    bank_ifsc: '',
    bank_name: '',
    documents: Object.fromEntries(allTypes.map((t) => [t, null])),
    extra_documents: [],
});
function onDoc(type: string, e: Event) {
    kycForm.documents[type] = (e.target as HTMLInputElement).files?.[0] ?? null;
}
function onExtra(e: Event) {
    kycForm.extra_documents = Array.from((e.target as HTMLInputElement).files ?? []);
}
function isRequired(d: Doc): boolean {
    return d.group === 'required' || (d.group === 'conditional' && kycForm.has_hypothecation);
}
const requiredDocs = computed<Doc[]>(() => props.docCatalog.filter(isRequired));
const optionalDocs = computed<Doc[]>(() => props.docCatalog.filter((d) => !isRequired(d)));
const sideLabel = (d: Doc, i: number) => (d.sides === 2 ? (i === 0 ? 'Front' : 'Back') : '');
const kycComplete = computed(
    () =>
        !!kycForm.owner_name &&
        !!kycForm.owner_phone &&
        !!kycForm.owner_address &&
        !!kycForm.chassis_number &&
        !!kycForm.bank_account_name &&
        !!kycForm.bank_account_number &&
        !!kycForm.bank_ifsc &&
        requiredDocs.value.every((d) => mediaTypes(d).every((t) => kycForm.documents[t])),
);
function submitKyc() {
    kycForm.post(`/vendor/submissions/${s.value.id}/owner-kyc`, { preserveScroll: true, forceFormData: true });
}

const docStatusStyle: Record<string, string> = {
    pending: 'bg-brand-orange/15 text-brand-orange',
    verified: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
    not_applicable: 'bg-muted text-muted-foreground',
};

// Request payment (bank already on file from KYC).
const reqForm = useForm({});
function requestPayment() {
    reqForm.post(`/vendor/submissions/${s.value.id}/request-payment`, { preserveScroll: true });
}

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    pending_review: 'bg-brand-orange/15 text-brand-orange',
    approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
};
const resultStyle: Record<string, string> = {
    pass: 'text-emerald-600',
    fail: 'text-brand-red',
    na: 'text-muted-foreground',
};
</script>

<template>
    <Head :title="s.submission_number" />

    <VendorLayout>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ s.make }} {{ s.model }} {{ s.variant }}</h1>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[s.status]">{{ s.status_label }}</span>
                </div>
                <p class="text-sm text-muted-foreground">{{ s.submission_number }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Button v-if="s.editable" as-child><Link :href="`/vendor/submissions/${s.id}/edit`">Edit</Link></Button>
                <Button variant="outline" as-child><Link href="/vendor/submissions">Back</Link></Button>
            </div>
        </div>

        <!-- Review outcome banners -->
        <Card v-if="s.status === 'approved'" class="mt-4 border-emerald-500/40 bg-emerald-500/5">
            <CardContent class="p-4 text-sm">
                <p class="font-medium text-emerald-700 dark:text-emerald-400">Approved</p>
                <p class="text-muted-foreground">
                    Your vehicle has been accepted into our purchase process<span v-if="s.review_remarks"> — {{ s.review_remarks }}</span
                    >.
                </p>
            </CardContent>
        </Card>
        <Card v-else-if="s.status === 'rejected'" class="mt-4 border-brand-red/40 bg-brand-red/5">
            <CardContent class="p-4 text-sm">
                <p class="font-medium text-brand-red">Rejected</p>
                <p class="text-muted-foreground">{{ s.review_remarks ?? 'This submission was not accepted.' }} You can edit and resubmit.</p>
            </CardContent>
        </Card>

        <!-- Settlement (after approval) -->
        <Card v-if="s.status === 'approved'" class="mt-4">
            <CardHeader class="flex flex-row items-center justify-between">
                <div>
                    <CardTitle class="text-base">Owner Documents, Agreement &amp; Payment</CardTitle>
                    <p class="mt-0.5 text-xs text-muted-foreground">{{ s.settlement_label }}</p>
                </div>
                <Button v-if="s.agreement_available" size="sm" variant="outline" as-child>
                    <a :href="s.agreement_url"><Download class="mr-1 size-4" /> Download Agreement</a>
                </Button>
            </CardHeader>
            <CardContent>
                <!-- Stage 1: submit owner details + bank + documents -->
                <template v-if="settlement === 'kyc_pending'">
                    <div v-if="s.kyc_remarks" class="mb-3 rounded-lg border border-brand-red/40 bg-brand-red/5 p-3 text-sm">
                        <p class="font-medium text-brand-red">Please correct and resubmit</p>
                        <p class="text-muted-foreground">{{ s.kyc_remarks }}</p>
                    </div>
                    <p class="mb-3 text-sm text-muted-foreground">
                        Provide the vehicle owner's details, chassis number, payout bank account, and upload the required documents. Once each
                        document is verified, your agreement (with Form 29 &amp; 30) will be available.
                    </p>

                    <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">Owner (Seller) &amp; vehicle</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5"><Label class="text-xs">Owner Name *</Label><Input v-model="kycForm.owner_name" class="h-9" /></div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Owner Phone *</Label><Input v-model="kycForm.owner_phone" class="h-9" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Owner Email</Label><Input v-model="kycForm.owner_email" type="email" class="h-9" />
                        </div>
                        <div class="grid gap-1.5"><Label class="text-xs">Owner PAN</Label><Input v-model="kycForm.owner_pan" class="h-9" /></div>
                        <div class="grid gap-1.5 sm:col-span-2">
                            <Label class="text-xs">Owner Address *</Label><Input v-model="kycForm.owner_address" class="h-9" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Chassis Number *</Label><Input v-model="kycForm.chassis_number" class="h-9" />
                        </div>
                        <label class="flex items-center gap-2 self-end text-sm">
                            <input v-model="kycForm.has_hypothecation" type="checkbox" class="size-4" />
                            Under hypothecation (bank loan)
                        </label>
                    </div>

                    <p class="mb-1 mt-4 text-xs font-semibold uppercase text-muted-foreground">Owner's bank account (payout)</p>
                    <p class="mb-2 text-xs text-muted-foreground">Payment is made to the vehicle owner's account. Enter the owner's bank details.</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Account Holder (Owner) *</Label
                            ><Input v-model="kycForm.bank_account_name" :placeholder="kycForm.owner_name || 'Owner name'" class="h-9" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label class="text-xs">Account Number *</Label><Input v-model="kycForm.bank_account_number" class="h-9" />
                        </div>
                        <div class="grid gap-1.5"><Label class="text-xs">IFSC *</Label><Input v-model="kycForm.bank_ifsc" class="h-9" /></div>
                        <div class="grid gap-1.5"><Label class="text-xs">Bank Name</Label><Input v-model="kycForm.bank_name" class="h-9" /></div>
                    </div>

                    <p class="mb-2 mt-4 text-xs font-semibold uppercase text-muted-foreground">Required documents</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="d in requiredDocs" :key="d.key" class="grid gap-2 rounded-lg border border-sidebar-border/60 p-2.5">
                            <Label class="text-xs font-medium">{{ d.label }} <span class="text-brand-red">*</span></Label>
                            <div v-for="(t, i) in mediaTypes(d)" :key="t" class="grid gap-1">
                                <span v-if="d.sides === 2" class="text-[11px] font-medium text-muted-foreground">{{ sideLabel(d, i) }}</span>
                                <input type="file" accept="image/*" class="text-sm" @change="onDoc(t, $event)" />
                                <p v-if="(kycForm.errors as any)[`documents.${t}`]" class="text-xs text-brand-red">
                                    {{ (kycForm.errors as any)[`documents.${t}`] }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <p class="mb-2 mt-4 text-xs font-semibold uppercase text-muted-foreground">Optional documents</p>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div v-for="d in optionalDocs" :key="d.key" class="grid gap-2 rounded-lg border border-sidebar-border/60 p-2.5">
                            <Label class="text-xs">{{ d.label }}</Label>
                            <div v-for="(t, i) in mediaTypes(d)" :key="t" class="grid gap-1">
                                <span v-if="d.sides === 2" class="text-[11px] font-medium text-muted-foreground">{{ sideLabel(d, i) }}</span>
                                <input type="file" accept="image/*" class="text-sm" @change="onDoc(t, $event)" />
                            </div>
                        </div>
                        <div class="grid gap-1.5 sm:col-span-2">
                            <Label class="text-xs">Other documents</Label>
                            <input type="file" accept="image/*" multiple class="text-sm" @change="onExtra" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <Button :disabled="!kycComplete || kycForm.processing" @click="submitKyc">
                            <Upload class="mr-1 size-4" /> Submit for Verification
                        </Button>
                        <p v-if="!kycComplete" class="mt-1 text-xs text-muted-foreground">
                            Fill all required fields and attach every required document to submit.
                        </p>
                    </div>
                </template>

                <!-- Stage 2: documents under review -->
                <template v-else-if="settlement === 'kyc_submitted'">
                    <div
                        class="mb-3 flex items-center gap-2 rounded-lg border border-brand-orange/40 bg-brand-orange/5 p-3 text-sm text-brand-orange"
                    >
                        <FileCheck2 class="size-4" /> Documents submitted — our team is verifying them.
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-sidebar-border/60 p-3 text-sm">
                            <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Owner &amp; vehicle</p>
                            <p>{{ s.owner_name }}</p>
                            <p class="text-muted-foreground">
                                {{ s.owner_phone }}<span v-if="s.owner_email"> · {{ s.owner_email }}</span>
                            </p>
                            <p class="text-muted-foreground">{{ s.owner_address }}</p>
                            <p v-if="s.chassis_number" class="text-muted-foreground">
                                Chassis {{ s.chassis_number }}<span v-if="s.has_hypothecation"> · under hypothecation</span>
                            </p>
                        </div>
                        <div class="rounded-lg border border-sidebar-border/60 p-3 text-sm">
                            <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Owner's bank account</p>
                            <p>{{ s.bank_account_name }}</p>
                            <p class="text-muted-foreground">
                                A/c {{ s.bank_account_number }} · {{ s.bank_ifsc }}<span v-if="s.bank_name"> · {{ s.bank_name }}</span>
                            </p>
                        </div>
                    </div>
                    <div v-if="s.documents?.rows?.length" class="mt-3 space-y-1">
                        <div
                            v-for="r in s.documents.rows"
                            :key="r.key"
                            class="flex items-center justify-between gap-2 rounded-md border border-sidebar-border/60 px-2.5 py-1.5 text-xs"
                        >
                            <span class="flex flex-wrap items-center gap-x-2 gap-y-1">
                                <span class="font-medium">{{ r.label }}</span>
                                <a v-for="(f, i) in r.files" :key="i" :href="f.url" target="_blank" class="text-muted-foreground underline">{{
                                    f.side || 'View'
                                }}</a>
                            </span>
                            <span
                                class="rounded-full px-2 py-0.5 font-medium capitalize"
                                :class="docStatusStyle[r.status] ?? 'bg-muted text-muted-foreground'"
                                >{{ (r.status || 'pending').replace('_', ' ') }}</span
                            >
                        </div>
                    </div>
                </template>

                <!-- Stage 3+: agreement ready / payment requested / paid -->
                <template v-else>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="rounded-lg border border-sidebar-border/60 p-3 text-sm">
                            <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Owner &amp; payout account</p>
                            <p v-if="s.owner_name">{{ s.owner_name }} · {{ s.owner_phone }}</p>
                            <p>{{ s.bank_account_name }}</p>
                            <p class="text-muted-foreground">
                                A/c {{ s.bank_account_number }} · {{ s.bank_ifsc }}<span v-if="s.bank_name"> · {{ s.bank_name }}</span>
                            </p>
                        </div>

                        <div
                            v-if="settlement === 'paid' || settlement === 'stocked'"
                            class="rounded-lg border border-emerald-500/40 bg-emerald-500/5 p-3 text-sm"
                        >
                            <p class="mb-1 text-xs font-medium uppercase text-emerald-700 dark:text-emerald-400">Payment received</p>
                            <p class="text-lg font-bold">{{ money(s.payment_amount) }}</p>
                            <p class="capitalize text-muted-foreground">
                                {{ s.payment_mode }}<span v-if="s.payment_reference"> · {{ s.payment_reference }}</span
                                ><span v-if="s.payment_date"> · {{ s.payment_date }}</span>
                            </p>
                            <a v-if="s.payment_proof" :href="s.payment_proof.url" target="_blank" class="mt-1 inline-block text-xs underline"
                                >View payment proof</a
                            >
                            <p
                                v-if="settlement === 'stocked'"
                                class="mt-2 border-t border-emerald-500/30 pt-2 text-xs text-emerald-700 dark:text-emerald-400"
                            >
                                ✓ Vehicle received and added to our inventory. This purchase is complete.
                            </p>
                        </div>
                        <div
                            v-else-if="settlement === 'payment_requested'"
                            class="flex items-center rounded-lg border border-brand-orange/40 bg-brand-orange/5 p-3 text-sm text-brand-orange"
                        >
                            Payment requested — our team is processing it.
                        </div>
                        <div
                            v-else
                            class="flex flex-col items-start justify-center gap-2 rounded-lg border border-emerald-500/40 bg-emerald-500/5 p-3 text-sm"
                        >
                            <p class="text-muted-foreground">Documents verified. Download your agreement, then request payment.</p>
                            <Button size="sm" :disabled="reqForm.processing" @click="requestPayment"
                                ><Upload class="mr-1 size-4" /> Request Payment</Button
                            >
                        </div>
                    </div>
                </template>
            </CardContent>
        </Card>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <Card>
                    <CardHeader><CardTitle class="text-base">Vehicle</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-y-2 text-sm sm:grid-cols-3">
                        <span class="text-muted-foreground">Year</span><span class="sm:col-span-2">{{ s.manufacturing_year ?? '—' }}</span>
                        <span class="text-muted-foreground">Reg. No.</span><span class="sm:col-span-2">{{ s.registration_number ?? '—' }}</span>
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
                        <p class="text-xs text-muted-foreground">Expected Amount</p>
                        <p class="text-2xl font-bold">{{ money(s.expected_amount) }}</p>
                        <p v-if="s.overall_rating" class="mt-1 text-sm text-muted-foreground">Overall: {{ s.overall_rating }}★</p>
                        <p v-if="s.overall_remark" class="mt-2 text-sm">{{ s.overall_remark }}</p>
                    </CardContent>
                </Card>
                <Card v-if="s.branch">
                    <CardContent class="p-4 text-sm">
                        <p class="text-xs text-muted-foreground">Preferred Branch</p>
                        <p class="font-medium">{{ s.branch.name }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </VendorLayout>
</template>
