<script setup lang="ts">
import ConfirmDialog from '@/components/ConfirmDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, CrudPermissions, Role } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Pencil, Plus, Trash2 } from 'lucide-vue-next';
import { ref } from 'vue';

defineProps<{
    roles: Role[];
    can: CrudPermissions;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles & Permissions', href: '/admin/roles' },
];

const scopeLabels: Record<string, string> = {
    all: 'All Company Records',
    selected_branches: 'Selected Branches',
    own_branch: 'Own Branch',
    own_department: 'Own Department',
    own_team: 'Own Team',
    assigned: 'Assigned Records',
    own: 'Own Records',
    read_only: 'Read Only',
};

const dialogOpen = ref(false);

const form = useForm({
    name: '',
    data_scope: 'own',
    description: '',
});

function submit() {
    form.post('/admin/roles', {
        onSuccess: () => {
            dialogOpen.value = false;
            form.reset();
        },
    });
}

const deleteTarget = ref<Role | null>(null);
const deleting = ref(false);

function confirmDelete() {
    if (!deleteTarget.value) return;
    deleting.value = true;
    router.delete(`/admin/roles/${deleteTarget.value.id}`, {
        preserveScroll: true,
        onFinish: () => {
            deleting.value = false;
            deleteTarget.value = null;
        },
    });
}
</script>

<template>
    <Head title="Roles & Permissions" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Roles &amp; Permissions</h1>
                    <p class="text-sm text-muted-foreground">Role definitions, data scopes and permission grants.</p>
                </div>
                <Button v-if="can.create" @click="dialogOpen = true">
                    <Plus class="mr-1 size-4" /> New Role
                </Button>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Role</th>
                            <th class="px-4 py-3 font-medium">Data Scope</th>
                            <th class="px-4 py-3 font-medium">Users</th>
                            <th class="px-4 py-3 font-medium">Permissions</th>
                            <th class="px-4 py-3 font-medium">Type</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="role in roles" :key="role.id" class="border-b last:border-0 hover:bg-muted/30">
                            <td class="px-4 py-3 font-medium">{{ role.name }}</td>
                            <td class="px-4 py-3">{{ scopeLabels[role.meta?.data_scope ?? 'own'] ?? role.meta?.data_scope }}</td>
                            <td class="px-4 py-3">{{ role.users_count ?? 0 }}</td>
                            <td class="px-4 py-3">
                                {{ role.name === 'Super Admin' ? 'All (implicit)' : (role.permissions_count ?? 0) }}
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full bg-muted px-2 py-0.5 text-xs">
                                    {{ role.meta?.is_system ? 'System' : 'Custom' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1">
                                    <Button
                                        v-if="can.update && role.name !== 'Super Admin'"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Edit role"
                                        as-child
                                    >
                                        <Link :href="`/admin/roles/${role.id}/edit`"><Pencil class="size-4" /></Link>
                                    </Button>
                                    <Button
                                        v-if="can.delete && !role.meta?.is_system"
                                        variant="ghost"
                                        size="icon"
                                        aria-label="Delete role"
                                        @click="deleteTarget = role"
                                    >
                                        <Trash2 class="size-4 text-destructive" />
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog v-model:open="dialogOpen">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>New Role</DialogTitle>
                </DialogHeader>
                <form class="grid gap-4" @submit.prevent="submit">
                    <div class="grid gap-2">
                        <Label for="role-name">Name *</Label>
                        <Input id="role-name" v-model="form.name" required maxlength="100" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="role-scope">Data Scope *</Label>
                        <select
                            id="role-scope"
                            v-model="form.data_scope"
                            required
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        >
                            <option v-for="(label, value) in scopeLabels" :key="value" :value="value">{{ label }}</option>
                        </select>
                        <InputError :message="form.errors.data_scope" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="role-desc">Description</Label>
                        <Input id="role-desc" v-model="form.description" />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="flex justify-end gap-2">
                        <Button type="button" variant="outline" @click="dialogOpen = false">Cancel</Button>
                        <Button type="submit" :disabled="form.processing">Create &amp; Configure</Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>

        <ConfirmDialog
            :open="deleteTarget !== null"
            title="Delete role?"
            :description="`This will remove '${deleteTarget?.name}'. Roles assigned to users cannot be deleted.`"
            :processing="deleting"
            @update:open="deleteTarget = $event ? deleteTarget : null"
            @confirm="confirmDelete"
        />
    </AppLayout>
</template>
