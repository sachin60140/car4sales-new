<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Branch, BreadcrumbItem, CrudPermissions, Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    branches: Paginated<Branch>;
    filters: { search: string };
    can: CrudPermissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Branches', href: '/admin/branches' },
];

const search = ref(props.filters.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/admin/branches', value ? { search: value } : {}, { preserveState: true, replace: true });
    }, 350);
});

const dialogOpen = ref(false);
const editing = ref<Branch | null>(null);

const form = useForm({
    code: '',
    name: '',
    address: '',
    city: '',
    state: '',
    pin_code: '',
    phone: '',
    email: '',
    gst_number: '',
    is_active: true as boolean,
    sort_order: 0,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(branch: Branch) {
    editing.value = branch;
    form.clearErrors();
    form.code = branch.code;
    form.name = branch.name;
    form.address = branch.address ?? '';
    form.city = branch.city ?? '';
    form.state = branch.state ?? '';
    form.pin_code = branch.pin_code ?? '';
    form.phone = branch.phone ?? '';
    form.email = branch.email ?? '';
    form.gst_number = branch.gst_number ?? '';
    form.is_active = branch.is_active;
    form.sort_order = branch.sort_order;
    dialogOpen.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    };

    if (editing.value) {
        form.put(`/admin/branches/${editing.value.id}`, options);
    } else {
        form.post('/admin/branches', options);
    }
}

const deleteTarget = ref<Branch | null>(null);
const deleting = ref(false);

function confirmDelete() {
    if (!deleteTarget.value) return;
    deleting.value = true;
    router.delete(`/admin/branches/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Branches" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Branches</h1>
                    <p class="text-sm text-muted-foreground">Manage dealership branch locations.</p>
                </div>
                <Button v-if="can.create" @click="openCreate">
                    <Plus class="mr-1 size-4" /> New Branch
                </Button>
            </div>

            <div class="relative w-full max-w-sm">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by name, code or city…" class="pl-9" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">City</th>
                            <th class="px-4 py-3 font-medium">Phone</th>
                            <th class="px-4 py-3 font-medium">Employees</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="branches.data.length === 0">
                            <td colspan="7" class="px-4 py-10 text-center text-muted-foreground">
                                No branches found. {{ can.create ? 'Create your first branch to get started.' : '' }}
                            </td>
                        </tr>
                        <tr v-for="branch in branches.data" :key="branch.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ branch.code }}</td>
                            <td class="px-4 py-3 font-medium">{{ branch.name }}</td>
                            <td class="px-4 py-3">{{ branch.city ?? '—' }}</td>
                            <td class="px-4 py-3">{{ branch.phone ?? '—' }}</td>
                            <td class="px-4 py-3">{{ branch.users_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="branch.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'"
                                >
                                    {{ branch.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button v-if="can.update" variant="ghost" size="icon" aria-label="Edit branch" @click="openEdit(branch)">
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button v-if="can.delete" variant="ghost" size="icon" aria-label="Delete branch" @click="deleteTarget = branch">
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="branches" />
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit Branch' : 'New Branch' }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="branch-code">Code *</Label>
                            <Input id="branch-code" v-model="form.code" required maxlength="20" />
                            <InputError :message="form.errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch-name">Name *</Label>
                            <Input id="branch-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="branch-address">Address</Label>
                        <Input id="branch-address" v-model="form.address" />
                        <InputError :message="form.errors.address" />
                    </div>
                    <div class="grid grid-cols-3 gap-4">
                        <div class="grid gap-2">
                            <Label for="branch-city">City</Label>
                            <Input id="branch-city" v-model="form.city" />
                            <InputError :message="form.errors.city" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch-state">State</Label>
                            <Input id="branch-state" v-model="form.state" />
                            <InputError :message="form.errors.state" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch-pin">PIN Code</Label>
                            <Input id="branch-pin" v-model="form.pin_code" maxlength="10" />
                            <InputError :message="form.errors.pin_code" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="branch-phone">Phone</Label>
                            <Input id="branch-phone" v-model="form.phone" maxlength="20" />
                            <InputError :message="form.errors.phone" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch-email">Email</Label>
                            <Input id="branch-email" v-model="form.email" type="email" />
                            <InputError :message="form.errors.email" />
                        </div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="branch-gst">GST Number</Label>
                            <Input id="branch-gst" v-model="form.gst_number" maxlength="20" />
                            <InputError :message="form.errors.gst_number" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="branch-sort">Sort Order</Label>
                            <Input id="branch-sort" v-model.number="form.sort_order" type="number" min="0" />
                            <InputError :message="form.errors.sort_order" />
                        </div>
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = $event === true" />
                        Active
                    </label>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save Changes' : 'Create Branch' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Delete branch?"
            :description="`This will remove '${deleteTarget?.name}'. Branches with employees cannot be deleted.`"
            :processing="deleting"
            @update:open="deleteTarget = $event ? deleteTarget : null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
