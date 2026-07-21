<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';

interface Partner {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    company_name: string | null;
    contact_person: string | null;
    city: string | null;
    gst_number: string | null;
    status: string;
    status_label: string;
}

interface KycDoc {
    id: number;
    file_path: string;
    status: string;
    number: string | null;
    remarks: string | null;
    original_name: string | null;
    verified_by_name: string | null;
    uploaded_at: string | null;
}
interface KycRow {
    type: string;
    label: string;
    group: string;
    document: KycDoc | null;
}
interface Kyc {
    rows: KycRow[];
    status: string;
    documentStatuses: { value: string; label: string }[];
}

const props = defineProps<{
    partner: Partner | null;
    kyc: Kyc | null;
}>();

const isEdit = computed(() => props.partner !== null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendor Partners', href: '/admin/vendor-partners' },
    { title: isEdit.value ? 'Edit Partner' : 'Add Partner', href: '#' },
];

const form = useForm({
    name: props.partner?.name ?? '',
    email: props.partner?.email ?? '',
    password: '',
    phone: props.partner?.phone ?? '',
    company_name: props.partner?.company_name ?? '',
    contact_person: props.partner?.contact_person ?? '',
    city: props.partner?.city ?? '',
    gst_number: props.partner?.gst_number ?? '',
});

function submit() {
    if (isEdit.value) {
        form.patch(`/admin/vendor-partners/${props.partner!.id}`);
    } else {
        form.post('/admin/vendor-partners');
    }
}

// --- KYC document rows (edit only) ---
const uploads = reactive<Record<string, { file: File | null; number: string }>>({});
const verifies = reactive<Record<string, { status: string; remarks: string }>>({});
if (props.kyc) {
    for (const row of props.kyc.rows) {
        uploads[row.type] = { file: null, number: row.document?.number ?? '' };
        verifies[row.type] = { status: row.document?.status ?? 'verified', remarks: row.document?.remarks ?? '' };
    }
}

const partnerId = computed(() => props.partner?.id);

function uploadDoc(type: string) {
    const u = uploads[type];
    if (!u?.file) return;
    router.post(
        `/admin/vendor-partners/${partnerId.value}/documents`,
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
        `/admin/vendor-partners/${partnerId.value}/documents/verify`,
        { type, status: vf.status, remarks: vf.remarks },
        { preserveScroll: true },
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
</script>

<template>
    <Head :title="isEdit ? 'Edit Vendor Partner' : 'Add Vendor Partner'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col gap-4 p-4">
            <form class="flex flex-col gap-4" @submit.prevent="submit">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h1 class="text-xl font-semibold">{{ isEdit ? 'Edit Vendor Partner' : 'Add Vendor Partner' }}</h1>
                        <p class="text-sm text-muted-foreground">
                            {{
                                isEdit
                                    ? 'Update the partner’s login and profile details.'
                                    : 'Create a sourcing partner. They start pending — upload & verify their KYC to activate them.'
                            }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <Button variant="outline" as-child>
                            <Link href="/admin/vendor-partners">Back</Link>
                        </Button>
                        <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Add Partner' }}</Button>
                    </div>
                </div>

                <Card>
                    <CardHeader><CardTitle>Login</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="name">Contact name <span class="text-brand-red">*</span></Label>
                            <Input id="name" v-model="form.name" placeholder="Full name" />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="email">Email <span class="text-brand-red">*</span></Label>
                            <Input id="email" v-model="form.email" type="email" placeholder="partner@example.com" />
                            <InputError :message="form.errors.email" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="phone">Phone</Label>
                            <Input id="phone" v-model="form.phone" placeholder="Mobile number" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="password">
                                {{ isEdit ? 'New password' : 'Password' }}<span v-if="!isEdit" class="text-brand-red"> *</span>
                            </Label>
                            <Input id="password" v-model="form.password" type="password" :placeholder="isEdit ? 'Leave blank to keep current' : ''" />
                            <InputError :message="form.errors.password" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Business profile</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <div class="grid gap-1.5">
                            <Label for="company_name">Company name</Label>
                            <Input id="company_name" v-model="form.company_name" placeholder="Dealership / firm" />
                            <InputError :message="form.errors.company_name" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="contact_person">Contact person</Label>
                            <Input id="contact_person" v-model="form.contact_person" placeholder="Defaults to contact name" />
                            <InputError :message="form.errors.contact_person" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="city">City</Label>
                            <Input id="city" v-model="form.city" />
                            <InputError :message="form.errors.city" />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="gst_number">GST number</Label>
                            <Input id="gst_number" v-model="form.gst_number" class="uppercase" />
                            <InputError :message="form.errors.gst_number" />
                        </div>
                    </CardContent>
                </Card>
            </form>

            <!-- KYC documents (edit only) -->
            <Card v-if="isEdit && kyc">
                <CardHeader class="flex flex-row items-center justify-between space-y-0">
                    <div>
                        <CardTitle>KYC Documents</CardTitle>
                        <p class="mt-1 text-sm text-muted-foreground">
                            Required documents must all be <strong>verified</strong> before the partner can be activated.
                        </p>
                    </div>
                    <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="kycStatusStyle[kyc.status]">
                        KYC {{ kyc.status }}
                    </span>
                </CardHeader>
                <CardContent class="overflow-x-auto">
                    <table class="w-full min-w-[820px] text-sm">
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
                                                :href="`/admin/files/${encodeURIComponent(row.document.file_path)}`"
                                                target="_blank"
                                                class="text-xs font-medium underline"
                                                >View file</a
                                            >
                                        </div>
                                        <div v-if="row.document.verified_by_name" class="mt-0.5 text-[11px] text-muted-foreground">
                                            by {{ row.document.verified_by_name }}
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
                                        <Input v-model="verifies[row.type].remarks" placeholder="Remarks" class="h-8 w-48 text-xs" />
                                        <Button size="sm" @click="verifyDoc(row.type)">Save</Button>
                                    </div>
                                    <span v-else class="text-xs text-muted-foreground">—</span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <p v-if="isEdit && partner" class="text-xs text-muted-foreground">
                Current status: <strong>{{ partner.status_label }}</strong> — change it with the Activate / Suspend actions on the partners list
                (Activate needs verified KYC).
            </p>
        </div>
    </AppLayout>
</template>
