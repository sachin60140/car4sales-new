<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

interface KycDoc {
    id: number;
    status: string;
    number: string | null;
    rejection_reason: string | null;
    verified_by_name: string | null;
    uploaded_at: string | null;
}
interface KycRow {
    type: string;
    label: string;
    group: string;
    document: KycDoc | null;
}

const props = defineProps<{
    customer: Record<string, any>;
    canViewKyc: boolean;
    kyc: { rows: KycRow[]; documentStatuses: { value: string; label: string }[] } | null;
    can: { update: boolean };
}>();

const c = computed(() => props.customer);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/admin/customers' },
    { title: c.value.customer_code, href: '#' },
];

// KYC document upload / verify (per row).
const uploads = reactive<Record<string, { file: File | null; number: string }>>({});
const verifies = reactive<Record<string, { status: string; rejection_reason: string }>>({});
if (props.kyc) {
    for (const row of props.kyc.rows) {
        uploads[row.type] = { file: null, number: row.document?.number ?? '' };
        verifies[row.type] = { status: row.document?.status ?? 'verified', rejection_reason: row.document?.rejection_reason ?? '' };
    }
}
function uploadDoc(type: string) {
    const u = uploads[type];
    if (!u?.file) return;
    router.post(
        `/admin/customers/${props.customer.id}/documents`,
        { type, file: u.file, number: u.number },
        {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                u.file = null;
            },
        },
    );
}
function verifyDoc(type: string) {
    const vf = verifies[type];
    router.post(
        `/admin/customers/${props.customer.id}/documents/verify`,
        { type, status: vf.status, rejection_reason: vf.rejection_reason },
        { preserveScroll: true },
    );
}
const docStatusStyle: Record<string, string> = {
    received: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    pending: 'bg-brand-orange/15 text-brand-orange',
    verified: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
};
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ customer.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ customer.customer_code }} · {{ customer.mobile }}<span v-if="customer.city"> · {{ customer.city }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="can.update" as-child>
                        <Link :href="`/admin/customers/${customer.id}/edit`">Edit</Link>
                    </Button>
                    <Button variant="outline" as-child><Link href="/admin/customers">Back</Link></Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader><CardTitle class="text-base">Profile</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                        <span class="text-muted-foreground">Email</span><span>{{ customer.email ?? '—' }}</span>
                        <span class="text-muted-foreground">Alt Mobile</span><span>{{ customer.alt_mobile ?? '—' }}</span>
                        <span class="text-muted-foreground">City</span><span>{{ customer.city ?? '—' }}</span>
                        <span class="text-muted-foreground">Occupation</span><span>{{ customer.occupation ?? '—' }}</span>
                        <span class="text-muted-foreground">KYC</span><span class="capitalize">{{ customer.kyc_status }}</span>
                        <span class="text-muted-foreground">Branch</span><span>{{ customer.branch?.name ?? '—' }}</span>
                    </CardContent>
                </Card>

                <Card v-if="canViewKyc">
                    <CardHeader><CardTitle class="text-base">Identity numbers</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                        <span class="text-muted-foreground">Aadhaar</span><span>{{ customer.aadhaar_number ?? '—' }}</span>
                        <span class="text-muted-foreground">PAN</span><span>{{ customer.pan_number ?? '—' }}</span>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader><CardTitle class="text-base">Lead History</CardTitle></CardHeader>
                    <CardContent>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th class="py-2 pr-3 font-medium">Lead #</th>
                                    <th class="py-2 pr-3 font-medium">Interest</th>
                                    <th class="py-2 pr-3 font-medium">Status</th>
                                    <th class="py-2 font-medium">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="l in customer.sales_leads" :key="l.id" class="border-b last:border-0">
                                    <td class="py-2 pr-3">
                                        <Link :href="`/admin/sales-leads/${l.id}`" class="font-mono text-xs underline">{{ l.lead_number }}</Link>
                                    </td>
                                    <td class="py-2 pr-3">
                                        {{ l.interested_vehicle ? `${l.interested_vehicle.make} ${l.interested_vehicle.model}` : '—' }}
                                    </td>
                                    <td class="py-2 pr-3 capitalize">{{ (l.status ?? '').replace(/_/g, ' ') }}</td>
                                    <td class="py-2">{{ new Date(l.created_at).toLocaleDateString() }}</td>
                                </tr>
                                <tr v-if="!customer.sales_leads?.length">
                                    <td colspan="4" class="py-4 text-center text-muted-foreground">No leads.</td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>

            <!-- KYC documents -->
            <Card v-if="canViewKyc && kyc">
                <CardHeader><CardTitle class="text-base">KYC Documents</CardTitle></CardHeader>
                <CardContent class="overflow-x-auto">
                    <table class="w-full min-w-[760px] text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="py-2 pr-3 font-medium">Document</th>
                                <th class="py-2 pr-3 font-medium">Attached</th>
                                <th class="py-2 pr-3 font-medium">Upload / replace</th>
                                <th class="py-2 pr-3 font-medium">Verify</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="row in kyc.rows" :key="row.type" class="border-b align-top last:border-0">
                                <td class="py-3 pr-3">
                                    <div class="font-medium">{{ row.label }}</div>
                                    <span v-if="row.group === 'required'" class="text-[10px] font-semibold uppercase tracking-wide text-brand-red"
                                        >Required</span
                                    >
                                    <span v-else class="text-[10px] uppercase tracking-wide text-muted-foreground">Optional</span>
                                </td>
                                <td class="py-3 pr-3">
                                    <template v-if="row.document">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="docStatusStyle[row.document.status]"
                                            >{{ row.document.status }}</span
                                        >
                                        <div class="mt-1">
                                            <a
                                                :href="`/admin/customer-documents/${row.document.id}`"
                                                target="_blank"
                                                class="text-xs font-medium underline"
                                                >View file</a
                                            >
                                        </div>
                                        <div v-if="row.document.number" class="mt-0.5 text-[11px] text-muted-foreground">
                                            No. {{ row.document.number }}
                                        </div>
                                        <div v-if="row.document.rejection_reason" class="mt-0.5 text-[11px] text-brand-red">
                                            {{ row.document.rejection_reason }}
                                        </div>
                                    </template>
                                    <span v-else class="text-xs text-muted-foreground">Not uploaded</span>
                                </td>
                                <td class="py-3 pr-3">
                                    <div class="flex flex-col gap-1.5">
                                        <input
                                            type="file"
                                            accept=".jpg,.jpeg,.png,.pdf"
                                            class="text-xs"
                                            @input="uploads[row.type].file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                        />
                                        <Input v-model="uploads[row.type].number" placeholder="Doc number (optional)" class="h-8 w-48 text-xs" />
                                        <Button size="sm" variant="outline" :disabled="!uploads[row.type].file" @click="uploadDoc(row.type)"
                                            >Upload</Button
                                        >
                                    </div>
                                </td>
                                <td class="py-3 pr-3">
                                    <div v-if="row.document" class="flex flex-col gap-1.5">
                                        <select
                                            v-model="verifies[row.type].status"
                                            class="h-8 w-32 rounded-md border border-input bg-transparent px-2 text-xs shadow-sm"
                                        >
                                            <option v-for="s in kyc.documentStatuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                                        </select>
                                        <Input
                                            v-if="verifies[row.type].status === 'rejected'"
                                            v-model="verifies[row.type].rejection_reason"
                                            placeholder="Reason"
                                            class="h-8 w-48 text-xs"
                                        />
                                        <Button size="sm" @click="verifyDoc(row.type)">Save</Button>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
