<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    role: {
        id: number;
        name: string;
        data_scope: string;
        description: string | null;
        is_system: boolean;
        permissions: string[];
    };
    registry: Record<string, string[]>;
    globalPermissions: string[];
    scopes: { value: string; label: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Roles & Permissions', href: '/admin/roles' },
    { title: props.role.name, href: '#' },
];

const form = useForm({
    data_scope: props.role.data_scope,
    description: props.role.description ?? '',
    permissions: [...props.role.permissions],
});

function permissionName(module: string, action: string): string {
    return `${module}.${action}`;
}

function togglePermission(name: string, checked: boolean) {
    if (checked && !form.permissions.includes(name)) {
        form.permissions.push(name);
    } else if (!checked) {
        form.permissions = form.permissions.filter((permission) => permission !== name);
    }
}

function moduleFullyGranted(module: string): boolean {
    return props.registry[module].every((action) => form.permissions.includes(permissionName(module, action)));
}

function toggleModule(module: string, checked: boolean) {
    const names = props.registry[module].map((action) => permissionName(module, action));

    if (checked) {
        form.permissions = [...new Set([...form.permissions, ...names])];
    } else {
        form.permissions = form.permissions.filter((permission) => !names.includes(permission));
    }
}

const grantedCount = computed(() => form.permissions.length);

function submit() {
    form.put(`/admin/roles/${props.role.id}`, { preserveScroll: true });
}
</script>

<template>
    <Head :title="`Role: ${role.name}`" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ role.name }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ role.is_system ? 'System role — permissions and scope are configurable, the role cannot be deleted.' : 'Custom role.' }}
                        {{ grantedCount }} permissions granted.
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child><Link href="/admin/roles">Back</Link></Button>
                    <Button type="submit" :disabled="form.processing">Save Role</Button>
                </div>
            </div>

            <Card>
                <CardHeader><CardTitle>Data Scope</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="role-scope">Records this role can see</Label>
                        <select
                            id="role-scope"
                            v-model="form.data_scope"
                            class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                        >
                            <option v-for="scope in scopes" :key="scope.value" :value="scope.value">{{ scope.label }}</option>
                        </select>
                        <InputError :message="form.errors.data_scope" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="role-desc">Description</Label>
                        <Input id="role-desc" v-model="form.description" />
                        <InputError :message="form.errors.description" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Permissions</CardTitle></CardHeader>
                <CardContent class="grid gap-6">
                    <InputError :message="form.errors.permissions" />
                    <div v-for="(actions, module) in registry" :key="module" class="border-b pb-4 last:border-0 last:pb-0">
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold capitalize">
                            <Checkbox
                                :model-value="moduleFullyGranted(module as string)"
                                @update:model-value="toggleModule(module as string, $event === true)"
                            />
                            {{ (module as string).replace(/-/g, ' ') }}
                        </label>
                        <div class="grid grid-cols-2 gap-2 pl-6 sm:grid-cols-4 lg:grid-cols-6">
                            <label v-for="action in actions" :key="action" class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    :model-value="form.permissions.includes(permissionName(module as string, action))"
                                    @update:model-value="togglePermission(permissionName(module as string, action), $event === true)"
                                />
                                {{ action.replace(/-/g, ' ') }}
                            </label>
                        </div>
                    </div>

                    <div>
                        <p class="mb-2 text-sm font-semibold">Global</p>
                        <div class="grid grid-cols-2 gap-2 pl-6 sm:grid-cols-4">
                            <label v-for="permission in globalPermissions" :key="permission" class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    :model-value="form.permissions.includes(permission)"
                                    @update:model-value="togglePermission(permission, $event === true)"
                                />
                                {{ permission.replace(/-/g, ' ') }}
                            </label>
                        </div>
                    </div>
                </CardContent>
            </Card>
        </form>
    </AppLayout>
</template>
