<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import VendorLayout from '@/layouts/VendorLayout.vue';
import { Head, router } from '@inertiajs/vue3';
import { reactive } from 'vue';

interface KycDoc {
    id: number;
    status: string;
    number: string | null;
    remarks: string | null;
    original_name: string | null;
    uploaded_at: string | null;
}
interface KycRow {
    type: string;
    label: string;
    group: string;
    document: KycDoc | null;
}

const props = defineProps<{
    kyc: { rows: KycRow[]; status: string };
    partner: { status: string; status_label: string };
}>();

const uploads = reactive<Record<string, { file: File | null; number: string }>>({});
for (const row of props.kyc.rows) {
    uploads[row.type] = { file: null, number: row.document?.number ?? '' };
}

function uploadDoc(type: string) {
    const u = uploads[type];
    if (!u?.file) return;
    router.post(
        '/vendor/kyc/documents',
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

const kycStatusStyle: Record<string, string> = {
    pending: 'bg-brand-orange/15 text-brand-orange',
    submitted: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    verified: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
};
const docStatusStyle: Record<string, string> = {
    pending: 'bg-brand-orange/15 text-brand-orange',
    verified: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
};
const kycMessage: Record<string, string> = {
    pending: 'Upload all required documents to complete your KYC.',
    submitted: 'Your documents are uploaded and awaiting verification by our team.',
    verified: 'Your KYC is verified. Once activated you can submit vehicles.',
};
</script>

<template>
    <Head title="KYC" />

    <VendorLayout>
        <div class="flex flex-col gap-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">KYC Documents</h1>
                    <p class="text-sm text-muted-foreground">{{ kycMessage[kyc.status] }}</p>
                </div>
                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="kycStatusStyle[kyc.status]">
                    KYC {{ kyc.status }}
                </span>
            </div>

            <div
                v-if="partner.status !== 'active'"
                class="rounded-md border border-brand-orange/30 bg-brand-orange/10 px-3 py-2 text-sm text-brand-orange"
            >
                Your account is <strong>{{ partner.status_label }}</strong
                >. Complete your KYC so an admin can activate you — you can only submit vehicles once active.
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Document</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Upload / replace</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in kyc.rows" :key="row.type" class="border-b align-top last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ row.label }}</div>
                                <span v-if="row.group === 'required'" class="text-[10px] font-semibold uppercase tracking-wide text-brand-red"
                                    >Required</span
                                >
                                <span v-else class="text-[10px] uppercase tracking-wide text-muted-foreground">Optional</span>
                            </td>
                            <td class="px-4 py-3">
                                <template v-if="row.document">
                                    <span
                                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                        :class="docStatusStyle[row.document.status]"
                                        >{{ row.document.status }}</span
                                    >
                                    <div class="mt-1">
                                        <a :href="`/vendor-partner-document/${row.document.id}`" target="_blank" class="text-xs font-medium underline"
                                            >View</a
                                        >
                                    </div>
                                    <div v-if="row.document.remarks" class="mt-0.5 text-[11px] text-brand-red">{{ row.document.remarks }}</div>
                                </template>
                                <span v-else class="text-xs text-muted-foreground">Not uploaded</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col gap-1.5">
                                    <input
                                        type="file"
                                        accept=".jpg,.jpeg,.png,.pdf"
                                        class="text-xs"
                                        @input="uploads[row.type].file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                                    />
                                    <Input v-model="uploads[row.type].number" placeholder="Doc number (optional)" class="h-8 w-52 text-xs" />
                                    <Button size="sm" variant="outline" :disabled="!uploads[row.type].file" @click="uploadDoc(row.type)"
                                        >Upload</Button
                                    >
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </VendorLayout>
</template>
