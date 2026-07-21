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
import type { BreadcrumbItem, CrudPermissions, Department, Paginated } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    departments: Paginated<Department>;
    filters: { search: string };
    can: CrudPermissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Departments', href: '/admin/departments' },
];

const search = ref(props.filters.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/admin/departments', value ? { search: value } : {}, { preserveState: true, replace: true });
    }, 350);
});

const dialogOpen = ref(false);
const editing = ref<Department | null>(null);

const form = useForm({
    code: '',
    name: '',
    description: '',
    is_active: true as boolean,
    sort_order: 0,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(department: Department) {
    editing.value = department;
    form.clearErrors();
    form.code = department.code;
    form.name = department.name;
    form.description = department.description ?? '';
    form.is_active = department.is_active;
    form.sort_order = department.sort_order;
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
        form.put(`/admin/departments/${editing.value.id}`, options);
    } else {
        form.post('/admin/departments', options);
    }
}

const deleteTarget = ref<Department | null>(null);
const deleting = ref(false);

function confirmDelete() {
    if (!deleteTarget.value) return;
    deleting.value = true;
    router.delete(`/admin/departments/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Departments" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Departments</h1>
                    <p class="text-sm text-muted-foreground">Configurable operating departments.</p>
                </div>
                <Button v-if="can.create" @click="openCreate"> <Plus class="mr-1 size-4" /> New Department </Button>
            </div>

            <div class="relative w-full max-w-sm">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by name or code…" class="pl-9" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[640px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Description</th>
                            <th class="px-4 py-3 font-medium">Employees</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="departments.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No departments found.</td>
                        </tr>
                        <tr v-for="department in departments.data" :key="department.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ department.code }}</td>
                            <td class="px-4 py-3 font-medium">{{ department.name }}</td>
                            <td class="px-4 py-3 text-muted-foreground">{{ department.description ?? '—' }}</td>
                            <td class="px-4 py-3">{{ department.users_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        department.is_active
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                    "
                                >
                                    {{ department.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button v-if="can.update" variant="ghost" size="icon" aria-label="Edit department" @click="openEdit(department)">
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button
                                        v-if="can.delete"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Delete department"
                                        @click="deleteTarget = department"
                                    >
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="departments" />
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit Department' : 'New Department' }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="dept-code">Code *</Label>
                            <Input id="dept-code" v-model="form.code" required maxlength="30" />
                            <InputError :message="form.errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="dept-sort">Sort Order</Label>
                            <Input id="dept-sort" v-model.number="form.sort_order" type="number" min="0" />
                            <InputError :message="form.errors.sort_order" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="dept-name">Name *</Label>
                        <Input id="dept-name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="dept-desc">Description</Label>
                        <Input id="dept-desc" v-model="form.description" />
                        <InputError :message="form.errors.description" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = $event === true" />
                        Active
                    </label>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save Changes' : 'Create Department' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Delete department?"
            :description="`This will remove '${deleteTarget?.name}'. Departments in use cannot be deleted.`"
            :processing="deleting"
            @update:open="deleteTarget = $event ? deleteTarget : null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
