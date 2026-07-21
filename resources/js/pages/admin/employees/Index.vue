<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Branch, BreadcrumbItem, CrudPermissions, Department, Employee, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

const props = defineProps<{
    employees: Paginated<Employee>;
    branches: Pick<Branch, 'id' | 'name'>[];
    departments: Pick<Department, 'id' | 'name'>[];
    filters: { search: string; branch_id: number | null; department_id: number | null; status: string | null };
    can: CrudPermissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/admin/employees' },
];

const filters = reactive({
    search: props.filters.search ?? '',
    branch_id: props.filters.branch_id ?? '',
    department_id: props.filters.department_id ?? '',
    status: props.filters.status ?? '',
});

let timer: ReturnType<typeof setTimeout> | null = null;

watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const query: Record<string, string> = {};
        if (filters.search) query.search = filters.search;
        if (filters.branch_id) query.branch_id = String(filters.branch_id);
        if (filters.department_id) query.department_id = String(filters.department_id);
        if (filters.status) query.status = filters.status;
        router.get('/admin/employees', query, { preserveState: true, replace: true });
    }, 350);
});

const deleteTarget = ref<Employee | null>(null);
const deleting = ref(false);

function confirmDelete() {
    if (!deleteTarget.value) return;
    deleting.value = true;
    router.delete(`/admin/employees/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Employees" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Employees</h1>
                    <p class="text-sm text-muted-foreground">Manage employee accounts, roles and postings.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/admin/employees/create"><Plus class="mr-1 size-4" /> New Employee</Link>
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Search name, email, phone…" class="pl-9" />
                </div>
                <select v-model="filters.branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All branches</option>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </select>
                <select v-model="filters.department_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All departments</option>
                    <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                </select>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[860px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Email</th>
                            <th class="px-4 py-3 font-medium">Branch</th>
                            <th class="px-4 py-3 font-medium">Department</th>
                            <th class="px-4 py-3 font-medium">Roles</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="employees.data.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">No employees found.</td>
                        </tr>
                        <tr v-for="employee in employees.data" :key="employee.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ employee.employee_profile?.employee_code ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ employee.name }}</div>
                                <div class="text-xs text-muted-foreground">{{ employee.employee_profile?.designation ?? '' }}</div>
                            </td>
                            <td class="px-4 py-3">{{ employee.email }}</td>
                            <td class="px-4 py-3">{{ employee.branch?.name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ employee.department?.name ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex max-w-56 flex-wrap gap-1">
                                    <span v-for="role in employee.roles" :key="role.id" class="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs">
                                        {{ role.name }}
                                    </span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="
                                        employee.is_active
                                            ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400'
                                            : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'
                                    "
                                >
                                    {{ employee.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button v-if="can.update" variant="ghost" size="icon" aria-label="Edit employee" as-child>
                                        <Link :href="`/admin/employees/${employee.id}/edit`"><Pencil class="size-4" /></Link>
                                    </Button>
                                    <Button
                                        v-if="can.delete"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Remove employee"
                                        @click="deleteTarget = employee"
                                    >
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="employees" />
        </div>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Remove employee?"
            :description="`This deactivates and archives '${deleteTarget?.name}'. Their records and history are preserved.`"
            confirm-label="Remove"
            :processing="deleting"
            @update:open="deleteTarget = $event ? deleteTarget : null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
