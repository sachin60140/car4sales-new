<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { reactive, ref, watch } from 'vue';

interface Enquiry {
    id: number;
    enquiry_number: string;
    type: string;
    type_label: string;
    name: string;
    mobile: string;
    city: string | null;
    message: string | null;
    vehicle?: { id: number; stock_number: string; make: string; model: string } | null;
    branch?: { id: number; name: string } | null;
    purchase_lead?: { id: number; lead_number: string } | null;
    status: string;
    created_at: string;
}

const props = defineProps<{
    enquiries: Paginated<Enquiry>;
    types: { value: string; label: string }[];
    branches: { id: number; name: string }[];
    filters: { type: string | null; status: string | null; branch_id: number | null };
    can: { update: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Website Enquiries', href: '/admin/website-enquiries' },
];

const filters = reactive({
    type: props.filters.type ?? '',
    status: props.filters.status ?? '',
    branch_id: props.filters.branch_id ?? '',
});
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.type) q.type = filters.type;
        if (filters.status) q.status = filters.status;
        if (filters.branch_id) q.branch_id = String(filters.branch_id);
        router.get('/admin/website-enquiries', q, { preserveState: true, replace: true });
    }, 300);
});

const editing = ref<Enquiry | null>(null);
const form = useForm({ status: 'new', remarks: '' });
function openEdit(e: Enquiry) {
    editing.value = e;
    form.status = e.status;
    form.remarks = '';
    form.clearErrors();
}
function save() {
    if (!editing.value) return;
    form.put(`/admin/website-enquiries/${editing.value.id}`, { preserveScroll: true, onSuccess: () => (editing.value = null) });
}

const typeStyle: Record<string, string> = {
    sell_car: 'bg-brand-yellow/20 text-brand-maroon dark:text-brand-yellow',
    vehicle: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    finance: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    test_drive: 'bg-purple-100 text-purple-700 dark:bg-purple-900/40 dark:text-purple-400',
};
const statusStyle: Record<string, string> = {
    new: 'bg-brand-orange/15 text-brand-orange',
    contacted: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    converted: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    closed: 'bg-muted text-muted-foreground',
    spam: 'bg-brand-red/15 text-brand-red',
};
</script>

<template>
    <Head title="Website Enquiries" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Website Enquiries</h1>
                <p class="text-sm text-muted-foreground">Leads captured from the public website.</p>
            </div>

            <div class="flex flex-wrap gap-3">
                <select v-model="filters.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All types</option>
                    <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option value="new">New</option><option value="contacted">Contacted</option>
                    <option value="converted">Converted</option><option value="closed">Closed</option><option value="spam">Spam</option>
                </select>
                <select v-model="filters.branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All branches</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[900px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Enquiry #</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Contact</th>
                            <th class="px-4 py-3 font-medium">Interest</th>
                            <th class="px-4 py-3 font-medium">Received</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="enquiries.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No enquiries yet.</td>
                        </tr>
                        <tr v-for="e in enquiries.data" :key="e.id" class="border-b align-top last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ e.enquiry_number }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="typeStyle[e.type] ?? 'bg-muted text-muted-foreground'">{{ e.type_label }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ e.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ e.mobile }}<span v-if="e.city"> · {{ e.city }}</span></div>
                            </td>
                            <td class="px-4 py-3 text-xs">
                                <div v-if="e.vehicle">{{ e.vehicle.stock_number }} — {{ e.vehicle.make }} {{ e.vehicle.model }}</div>
                                <Link v-if="e.purchase_lead" :href="`/admin/purchase-leads/${e.purchase_lead.id}`" class="text-brand-maroon underline dark:text-brand-yellow">{{ e.purchase_lead.lead_number }}</Link>
                                <div v-if="e.message" class="max-w-xs truncate text-muted-foreground" :title="e.message">{{ e.message }}</div>
                            </td>
                            <td class="whitespace-nowrap px-4 py-3 text-xs text-muted-foreground">{{ new Date(e.created_at).toLocaleString() }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="statusStyle[e.status] ?? 'bg-muted'">{{ e.status }}</span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <Button v-if="can.update" variant="ghost" size="sm" @click="openEdit(e)">Update</Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="enquiries" />
        </div>

        <Dialog :open="editing !== null" @update:open="editing = $event ? editing : null">
            <DialogContent class="sm:max-w-md">
                <DialogHeader><DialogTitle>Update {{ editing?.enquiry_number }}</DialogTitle></DialogHeader>
                <form class="grid gap-4" @submit.prevent="save">
                    <div class="grid gap-2">
                        <Label for="enq-status">Status</Label>
                        <select id="enq-status" v-model="form.status" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option value="new">New</option><option value="contacted">Contacted</option>
                            <option value="converted">Converted</option><option value="closed">Closed</option><option value="spam">Spam</option>
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="enq-remarks">Remarks</Label>
                        <textarea id="enq-remarks" v-model="form.remarks" rows="3" class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="editing = null">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Save</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
