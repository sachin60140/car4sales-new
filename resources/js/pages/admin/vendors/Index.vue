<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CrudPermissions, Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface Vendor {
    id: number;
    code: string;
    name: string;
    type: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    city: string | null;
    gst_number: string | null;
    branch_id: number | null;
    is_active: boolean;
    remarks: string | null;
}

const props = defineProps<{
    vendors: Paginated<Vendor>;
    branches: { id: number; name: string }[];
    filters: { search: string; type: string | null };
    can: CrudPermissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendors', href: '/admin/vendors' },
];

const types = [
    { value: 'workshop', label: 'Workshop' },
    { value: 'parts', label: 'Parts' },
    { value: 'rto_agent', label: 'RTO Agent' },
    { value: 'transport', label: 'Transport' },
    { value: 'other', label: 'Other' },
];

const filters = reactive({ search: props.filters.search ?? '', type: props.filters.type ?? '' });
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.type) q.type = filters.type;
        router.get('/admin/vendors', q, { preserveState: true, replace: true });
    }, 350);
});

const dialogOpen = ref(false);
const editing = ref<Vendor | null>(null);
const form = useForm({
    name: '', type: 'workshop', contact_person: '', phone: '', email: '',
    address: '', city: '', gst_number: '', branch_id: null as number | null,
    is_active: true as boolean, remarks: '',
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}
function openEdit(vendor: Vendor) {
    editing.value = vendor;
    form.clearErrors();
    form.name = vendor.name;
    form.type = vendor.type;
    form.contact_person = vendor.contact_person ?? '';
    form.phone = vendor.phone ?? '';
    form.email = vendor.email ?? '';
    form.city = vendor.city ?? '';
    form.gst_number = vendor.gst_number ?? '';
    form.branch_id = vendor.branch_id;
    form.is_active = vendor.is_active;
    form.remarks = vendor.remarks ?? '';
    dialogOpen.value = true;
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { dialogOpen.value = false; form.reset(); } };
    if (editing.value) form.put(`/admin/vendors/${editing.value.id}`, opts);
    else form.post('/admin/vendors', opts);
}

const deleteTarget = ref<Vendor | null>(null);
const deleting = ref(false);
function confirmDelete() {
    if (!deleteTarget.value) return;
    deleting.value = true;
    router.delete(`/admin/vendors/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => { deleting.value = false; deleteTarget.value = null; },
    });
}
</script>

<template>
    <Head title="Vendors" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Vendors</h1>
                    <p class="text-sm text-muted-foreground">Workshops, parts suppliers, RTO agents and transporters.</p>
                </div>
                <Button v-if="can.create" @click="openCreate"><Plus class="mr-1 size-4" /> New Vendor</Button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Name, code, phone…" class="pl-9" />
                </div>
                <select v-model="filters.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All types</option>
                    <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[760px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Contact</th>
                            <th class="px-4 py-3 font-medium">City</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="vendors.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">No vendors found.</td>
                        </tr>
                        <tr v-for="vendor in vendors.data" :key="vendor.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ vendor.code }}</td>
                            <td class="px-4 py-3 font-medium">{{ vendor.name }}</td>
                            <td class="px-4 py-3 capitalize">{{ vendor.type.replace('_', ' ') }}</td>
                            <td class="px-4 py-3">{{ vendor.phone ?? '—' }}</td>
                            <td class="px-4 py-3">{{ vendor.city ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="vendor.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-brand-red/15 text-brand-red'">
                                    {{ vendor.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button v-if="can.update" variant="ghost" size="icon" aria-label="Edit vendor" @click="openEdit(vendor)"><Pencil class="size-4" /></Button>
                                    <Button v-if="can.delete" variant="ghost" size="icon" aria-label="Delete vendor" @click="deleteTarget = vendor"><Trash2 class="size-4 text-destructive" /></Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="vendors" />
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader><DialogTitle>{{ editing ? 'Edit Vendor' : 'New Vendor' }}</DialogTitle></DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="v-name">Name *</Label>
                            <Input id="v-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="v-type">Type *</Label>
                            <select id="v-type" v-model="form.type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2"><Label for="v-contact">Contact Person</Label><Input id="v-contact" v-model="form.contact_person" /></div>
                        <div class="grid gap-2"><Label for="v-phone">Phone</Label><Input id="v-phone" v-model="form.phone" maxlength="20" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2"><Label for="v-email">Email</Label><Input id="v-email" v-model="form.email" type="email" /></div>
                        <div class="grid gap-2"><Label for="v-city">City</Label><Input id="v-city" v-model="form.city" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2"><Label for="v-gst">GST Number</Label><Input id="v-gst" v-model="form.gst_number" maxlength="20" /></div>
                        <div class="grid gap-2">
                            <Label for="v-branch">Branch</Label>
                            <select id="v-branch" v-model="form.branch_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option :value="null">All branches</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = $event === true" /> Active
                    </label>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">{{ editing ? 'Save Changes' : 'Create Vendor' }}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Delete vendor?"
            :description="`This will remove '${deleteTarget?.name}'.`"
            :processing="deleting"
            @update:open="deleteTarget = $event ? deleteTarget : null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
