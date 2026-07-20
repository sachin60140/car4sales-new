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
import { ref, watch } from 'vue';

interface Lender {
    id: number;
    code: string;
    name: string;
    type: string;
    contact_person: string | null;
    phone: string | null;
    email: string | null;
    base_interest_rate: string | null;
    is_active: boolean;
}

const props = defineProps<{
    lenders: Paginated<Lender>;
    filters: { search: string };
    can: CrudPermissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Lenders', href: '/admin/lenders' },
];

const search = ref(props.filters.search ?? '');
let timer: ReturnType<typeof setTimeout> | null = null;
watch(search, (v) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => router.get('/admin/lenders', v ? { search: v } : {}, { preserveState: true, replace: true }), 350);
});

const types = [
    { value: 'bank', label: 'Bank' },
    { value: 'nbfc', label: 'NBFC' },
    { value: 'captive', label: 'Captive' },
    { value: 'other', label: 'Other' },
];

const dialogOpen = ref(false);
const editing = ref<Lender | null>(null);
const form = useForm({ name: '', type: 'bank', contact_person: '', phone: '', email: '', base_interest_rate: null as number | null, is_active: true as boolean });

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}
function openEdit(lender: Lender) {
    editing.value = lender;
    form.clearErrors();
    form.name = lender.name;
    form.type = lender.type;
    form.contact_person = lender.contact_person ?? '';
    form.phone = lender.phone ?? '';
    form.email = lender.email ?? '';
    form.base_interest_rate = lender.base_interest_rate ? Number(lender.base_interest_rate) : null;
    form.is_active = lender.is_active;
    dialogOpen.value = true;
}
function submit() {
    const opts = { preserveScroll: true, onSuccess: () => { dialogOpen.value = false; form.reset(); } };
    if (editing.value) form.put(`/admin/lenders/${editing.value.id}`, opts);
    else form.post('/admin/lenders', opts);
}

const deleteTarget = ref<Lender | null>(null);
const deleting = ref(false);
function confirmDelete() {
    if (!deleteTarget.value) return;
    deleting.value = true;
    router.delete(`/admin/lenders/${deleteTarget.value.id}`, { preserveScroll: true, onFinish: () => { deleting.value = false; deleteTarget.value = null; } });
}
</script>

<template>
    <Head title="Lenders" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Lenders</h1>
                    <p class="text-sm text-muted-foreground">Finance partners — banks and NBFCs.</p>
                </div>
                <Button v-if="can.create" @click="openCreate"><Plus class="mr-1 size-4" /> New Lender</Button>
            </div>

            <div class="relative w-full max-w-sm">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Name or code…" class="pl-9" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 font-medium">Rate</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="lenders.data.length === 0"><td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No lenders yet.</td></tr>
                        <tr v-for="l in lenders.data" :key="l.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ l.code }}</td>
                            <td class="px-4 py-3 font-medium">{{ l.name }}</td>
                            <td class="px-4 py-3 uppercase">{{ l.type }}</td>
                            <td class="px-4 py-3">{{ l.base_interest_rate ? l.base_interest_rate + '%' : '—' }}</td>
                            <td class="px-4 py-3"><span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="l.is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400' : 'bg-brand-red/15 text-brand-red'">{{ l.is_active ? 'Active' : 'Inactive' }}</span></td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button v-if="can.update" variant="ghost" size="icon" aria-label="Edit lender" @click="openEdit(l)"><Pencil class="size-4" /></Button>
                                    <Button v-if="can.delete" variant="ghost" size="icon" aria-label="Delete lender" @click="deleteTarget = l"><Trash2 class="size-4 text-destructive" /></Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="lenders" />
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader><DialogTitle>{{ editing ? 'Edit Lender' : 'New Lender' }}</DialogTitle></DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="l-name">Name *</Label>
                            <Input id="l-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="l-type">Type *</Label>
                            <select id="l-type" v-model="form.type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option v-for="t in types" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2"><Label for="l-contact">Contact Person</Label><Input id="l-contact" v-model="form.contact_person" /></div>
                        <div class="grid gap-2"><Label for="l-phone">Phone</Label><Input id="l-phone" v-model="form.phone" maxlength="20" /></div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2"><Label for="l-email">Email</Label><Input id="l-email" v-model="form.email" type="email" /></div>
                        <div class="grid gap-2"><Label for="l-rate">Base Rate (%)</Label><Input id="l-rate" v-model.number="form.base_interest_rate" type="number" step="0.01" /></div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = $event === true" /> Active
                    </label>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">{{ editing ? 'Save Changes' : 'Create Lender' }}</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Delete lender?"
            :description="`This will remove '${deleteTarget?.name}'.`"
            :processing="deleting"
            @update:open="deleteTarget = $event ? deleteTarget : null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
