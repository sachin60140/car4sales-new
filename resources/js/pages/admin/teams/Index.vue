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
import type { Branch, BreadcrumbItem, CrudPermissions, Department, Paginated, Team } from '@/types';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Search, Trash2 } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const props = defineProps<{
    teams: Paginated<Team>;
    branches: Pick<Branch, 'id' | 'name'>[];
    departments: Pick<Department, 'id' | 'name'>[];
    leaders: { id: number; name: string }[];
    filters: { search: string; branch_id: number | null };
    can: CrudPermissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Teams', href: '/admin/teams' },
];

const search = ref(props.filters.search ?? '');
let searchTimer: ReturnType<typeof setTimeout> | null = null;

watch(search, (value) => {
    if (searchTimer) clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/admin/teams', value ? { search: value } : {}, { preserveState: true, replace: true });
    }, 350);
});

const dialogOpen = ref(false);
const editing = ref<Team | null>(null);

const form = useForm({
    code: '',
    name: '',
    branch_id: null as number | null,
    department_id: null as number | null,
    team_leader_id: null as number | null,
    is_active: true as boolean,
});

function openCreate() {
    editing.value = null;
    form.reset();
    form.clearErrors();
    dialogOpen.value = true;
}

function openEdit(team: Team) {
    editing.value = team;
    form.clearErrors();
    form.code = team.code;
    form.name = team.name;
    form.branch_id = team.branch_id;
    form.department_id = team.department_id;
    form.team_leader_id = team.team_leader_id;
    form.is_active = team.is_active;
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
        form.put(`/admin/teams/${editing.value.id}`, options);
    } else {
        form.post('/admin/teams', options);
    }
}

const deleteTarget = ref<Team | null>(null);
const deleting = ref(false);

function confirmDelete() {
    if (!deleteTarget.value) return;
    deleting.value = true;
    router.delete(`/admin/teams/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Teams" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Teams</h1>
                    <p class="text-sm text-muted-foreground">Branch and department level working teams.</p>
                </div>
                <Button v-if="can.create" @click="openCreate">
                    <Plus class="mr-1 size-4" /> New Team
                </Button>
            </div>

            <div class="relative w-full max-w-sm">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Search by name or code…" class="pl-9" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Branch</th>
                            <th class="px-4 py-3 font-medium">Department</th>
                            <th class="px-4 py-3 font-medium">Team Leader</th>
                            <th class="px-4 py-3 font-medium">Members</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="teams.data.length === 0">
                            <td colspan="8" class="px-4 py-10 text-center text-muted-foreground">No teams found.</td>
                        </tr>
                        <tr v-for="team in teams.data" :key="team.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-mono text-xs">{{ team.code }}</td>
                            <td class="px-4 py-3 font-medium">{{ team.name }}</td>
                            <td class="px-4 py-3">{{ team.branch?.name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ team.department?.name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ team.team_leader?.name ?? '—' }}</td>
                            <td class="px-4 py-3">{{ team.members_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                                    :class="team.is_active ? 'bg-green-100 text-green-700 dark:bg-green-900/40 dark:text-green-400' : 'bg-red-100 text-red-700 dark:bg-red-900/40 dark:text-red-400'"
                                >
                                    {{ team.is_active ? 'Active' : 'Inactive' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button v-if="can.update" variant="ghost" size="icon" aria-label="Edit team" @click="openEdit(team)">
                                        <Pencil class="size-4" />
                                    </Button>
                                    <Button v-if="can.delete" variant="ghost" size="icon" aria-label="Delete team" @click="deleteTarget = team">
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="teams" />
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{{ editing ? 'Edit Team' : 'New Team' }}</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid grid-cols-2 gap-4">
                        <div class="grid gap-2">
                            <Label for="team-code">Code *</Label>
                            <Input id="team-code" v-model="form.code" required maxlength="30" />
                            <InputError :message="form.errors.code" />
                        </div>
                        <div class="grid gap-2">
                            <Label for="team-name">Name *</Label>
                            <Input id="team-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                    </div>
                    <div class="grid gap-2">
                        <Label for="team-branch">Branch *</Label>
                        <select
                            id="team-branch"
                            v-model="form.branch_id"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        >
                            <option :value="null" disabled>Select branch…</option>
                            <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                        </select>
                        <InputError :message="form.errors.branch_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="team-department">Department *</Label>
                        <select
                            id="team-department"
                            v-model="form.department_id"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        >
                            <option :value="null" disabled>Select department…</option>
                            <option v-for="department in departments" :key="department.id" :value="department.id">{{ department.name }}</option>
                        </select>
                        <InputError :message="form.errors.department_id" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="team-leader">Team Leader</Label>
                        <select
                            id="team-leader"
                            v-model="form.team_leader_id"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        >
                            <option :value="null">None</option>
                            <option v-for="leader in leaders" :key="leader.id" :value="leader.id">{{ leader.name }}</option>
                        </select>
                        <InputError :message="form.errors.team_leader_id" />
                    </div>
                    <label class="flex items-center gap-2 text-sm">
                        <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = $event === true" />
                        Active
                    </label>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">
                            {{ editing ? 'Save Changes' : 'Create Team' }}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Delete team?"
            :description="`This will remove '${deleteTarget?.name}'. Teams with members cannot be deleted.`"
            :processing="deleting"
            @update:open="deleteTarget = $event ? deleteTarget : null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
