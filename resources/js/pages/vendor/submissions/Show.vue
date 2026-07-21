<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendorLayout from '@/layouts/VendorLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Download, Upload } from 'lucide-vue-next';
import { computed } from 'vue';

const props = defineProps<{ submission: Record<string, any> }>();
const s = computed(() => props.submission);

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

// --- Settlement (post-approval) ---
const settlement = computed<string>(() => s.value.settlement_status);
const payForm = useForm<{ bank_account_name: string; bank_account_number: string; bank_ifsc: string; bank_name: string; cheque: File | null }>({
    bank_account_name: '', bank_account_number: '', bank_ifsc: '', bank_name: '', cheque: null,
});
function onCheque(e: Event) {
    payForm.cheque = (e.target as HTMLInputElement).files?.[0] ?? null;
}
function requestPayment() {
    payForm.post(`/vendor/submissions/${s.value.id}/request-payment`, { preserveScroll: true, forceFormData: true });
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
                <p class="text-muted-foreground">Your vehicle has been accepted into our purchase process<span v-if="s.review_remarks"> — {{ s.review_remarks }}</span>.</p>
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
                <CardTitle class="text-base">Agreement &amp; Payment</CardTitle>
                <Button size="sm" variant="outline" as-child>
                    <a :href="s.agreement_url"><Download class="mr-1 size-4" /> Download Agreement</a>
                </Button>
            </CardHeader>
            <CardContent>
                <p class="mb-3 text-sm text-muted-foreground">
                    Download your pre-filled agreement (with Form 29 &amp; 30), then request payment with your bank details.
                </p>

                <!-- Request payment -->
                <div v-if="settlement === 'agreement_ready'" class="grid gap-3 rounded-lg border border-sidebar-border/60 p-3 sm:grid-cols-2">
                    <div class="grid gap-1.5"><Label class="text-xs">Account Holder Name *</Label><Input v-model="payForm.bank_account_name" class="h-9" /></div>
                    <div class="grid gap-1.5"><Label class="text-xs">Account Number *</Label><Input v-model="payForm.bank_account_number" class="h-9" /></div>
                    <div class="grid gap-1.5"><Label class="text-xs">IFSC *</Label><Input v-model="payForm.bank_ifsc" class="h-9" /></div>
                    <div class="grid gap-1.5"><Label class="text-xs">Bank Name</Label><Input v-model="payForm.bank_name" class="h-9" /></div>
                    <div class="grid gap-1.5 sm:col-span-2">
                        <Label class="text-xs">Cancelled Cheque *</Label>
                        <input type="file" accept="image/*" class="text-sm" @change="onCheque" />
                    </div>
                    <div class="sm:col-span-2">
                        <Button :disabled="!payForm.bank_account_name || !payForm.bank_account_number || !payForm.bank_ifsc || !payForm.cheque || payForm.processing" @click="requestPayment">
                            <Upload class="mr-1 size-4" /> Request Payment
                        </Button>
                    </div>
                </div>

                <!-- Requested / paid states -->
                <div v-else class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-lg border border-sidebar-border/60 p-3 text-sm">
                        <p class="mb-1 text-xs font-medium uppercase text-muted-foreground">Bank details</p>
                        <p>{{ s.bank_account_name }}</p>
                        <p class="text-muted-foreground">A/c {{ s.bank_account_number }} · {{ s.bank_ifsc }}<span v-if="s.bank_name"> · {{ s.bank_name }}</span></p>
                        <a v-if="s.cheque" :href="s.cheque.url" target="_blank" class="mt-1 inline-block text-xs underline">View cancelled cheque</a>
                    </div>
                    <div v-if="settlement === 'paid'" class="rounded-lg border border-emerald-500/40 bg-emerald-500/5 p-3 text-sm">
                        <p class="mb-1 text-xs font-medium uppercase text-emerald-700 dark:text-emerald-400">Payment received</p>
                        <p class="text-lg font-bold">{{ money(s.payment_amount) }}</p>
                        <p class="text-muted-foreground capitalize">{{ s.payment_mode }}<span v-if="s.payment_reference"> · {{ s.payment_reference }}</span><span v-if="s.payment_date"> · {{ s.payment_date }}</span></p>
                        <a v-if="s.payment_proof" :href="s.payment_proof.url" target="_blank" class="mt-1 inline-block text-xs underline">View payment proof</a>
                    </div>
                    <div v-else class="flex items-center rounded-lg border border-brand-orange/40 bg-brand-orange/5 p-3 text-sm text-brand-orange">
                        Payment requested — our team is processing it.
                    </div>
                </div>
            </CardContent>
        </Card>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <Card>
                    <CardHeader><CardTitle class="text-base">Vehicle</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-y-2 text-sm sm:grid-cols-3">
                        <span class="text-muted-foreground">Year</span><span class="sm:col-span-2">{{ s.manufacturing_year ?? '—' }}</span>
                        <span class="text-muted-foreground">Reg. No.</span><span class="sm:col-span-2">{{ s.registration_number ?? '—' }}</span>
                        <span class="text-muted-foreground">Fuel / Trans.</span><span class="sm:col-span-2">{{ s.fuel_type ?? '—' }} / {{ s.transmission ?? '—' }}</span>
                        <span class="text-muted-foreground">Odometer</span><span class="sm:col-span-2">{{ s.odometer_km ? Number(s.odometer_km).toLocaleString('en-IN') + ' km' : '—' }}</span>
                        <span class="text-muted-foreground">Colour</span><span class="sm:col-span-2">{{ s.color ?? '—' }}</span>
                        <span class="text-muted-foreground">Owner No.</span><span class="sm:col-span-2">{{ s.ownership_serial ?? '—' }}</span>
                    </CardContent>
                </Card>

                <Card v-if="s.items?.length">
                    <CardHeader><CardTitle class="text-base">Condition Report</CardTitle></CardHeader>
                    <CardContent>
                        <ul class="divide-y text-sm">
                            <li v-for="it in s.items" :key="it.id" class="flex items-center justify-between gap-2 py-2">
                                <span>{{ it.label }} <span class="text-xs text-muted-foreground">· {{ it.section }}</span><span v-if="it.remarks" class="text-xs text-muted-foreground"> — {{ it.remarks }}</span></span>
                                <span class="flex items-center gap-2">
                                    <span v-if="it.rating" class="text-xs text-muted-foreground">{{ it.rating }}★</span>
                                    <span class="font-medium uppercase" :class="resultStyle[it.result]">{{ it.result === 'na' ? 'N/A' : it.result }}</span>
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
