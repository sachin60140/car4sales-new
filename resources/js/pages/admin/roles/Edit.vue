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
import { computed, ref } from 'vue';

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

// --- "Show granted only" filter ---
const showGrantedOnly = ref(false);
function grantedActions(module: string): string[] {
    return props.registry[module].filter((action) => form.permissions.includes(permissionName(module, action)));
}
function moduleGrantedCount(module: string): number {
    return grantedActions(module).length;
}
function actionsFor(module: string): string[] {
    return showGrantedOnly.value ? grantedActions(module) : props.registry[module];
}
const visibleModules = computed<string[]>(() =>
    Object.keys(props.registry).filter((module) => !showGrantedOnly.value || moduleGrantedCount(module) > 0),
);
const visibleGlobals = computed<string[]>(() =>
    showGrantedOnly.value ? props.globalPermissions.filter((p) => form.permissions.includes(p)) : props.globalPermissions,
);
const hasAnyVisible = computed(() => visibleModules.value.length > 0 || visibleGlobals.value.length > 0);

// Live summary of everything currently granted, grouped by module.
const grantedByModule = computed<{ module: string; actions: string[] }[]>(() =>
    Object.keys(props.registry)
        .map((module) => ({ module, actions: grantedActions(module) }))
        .filter((group) => group.actions.length > 0),
);
const grantedGlobals = computed<string[]>(() => props.globalPermissions.filter((p) => form.permissions.includes(p)));

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
                <CardHeader class="flex flex-row items-center justify-between gap-3">
                    <CardTitle>Permissions</CardTitle>
                    <label class="flex items-center gap-2 text-sm font-normal text-muted-foreground">
                        <Checkbox :model-value="showGrantedOnly" @update:model-value="showGrantedOnly = $event === true" />
                        Show granted only
                    </label>
                </CardHeader>
                <CardContent class="grid gap-6">
                    <InputError :message="form.errors.permissions" />

                    <!-- Live summary of everything this role currently grants. -->
                    <div v-if="grantedCount" class="rounded-lg border border-sidebar-border/60 bg-muted/30 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase text-muted-foreground">Granted access ({{ grantedCount }})</p>
                        <div class="flex flex-col gap-1.5">
                            <div v-for="group in grantedByModule" :key="group.module" class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm">
                                <span class="min-w-32 font-medium capitalize">{{ group.module.replace(/-/g, ' ') }}</span>
                                <span class="flex flex-wrap gap-1">
                                    <span v-for="action in group.actions" :key="action" class="rounded-full border bg-background px-2 py-0.5 text-xs text-muted-foreground">{{ action.replace(/-/g, ' ') }}</span>
                                </span>
                            </div>
                            <div v-if="grantedGlobals.length" class="flex flex-wrap items-baseline gap-x-2 gap-y-1 text-sm">
                                <span class="min-w-32 font-medium">Global</span>
                                <span class="flex flex-wrap gap-1">
                                    <span v-for="permission in grantedGlobals" :key="permission" class="rounded-full border bg-background px-2 py-0.5 text-xs text-muted-foreground">{{ permission.replace(/-/g, ' ') }}</span>
                                </span>
                            </div>
                        </div>
                    </div>

                    <p v-if="!hasAnyVisible" class="text-sm text-muted-foreground">No permissions granted yet.</p>

                    <div v-for="module in visibleModules" :key="module" class="border-b pb-4 last:border-0 last:pb-0">
                        <label class="mb-2 flex items-center gap-2 text-sm font-semibold capitalize">
                            <Checkbox
                                v-if="!showGrantedOnly"
                                :model-value="moduleFullyGranted(module)"
                                @update:model-value="toggleModule(module, $event === true)"
                            />
                            {{ module.replace(/-/g, ' ') }}
                            <span class="text-xs font-normal normal-case text-muted-foreground">{{ moduleGrantedCount(module) }}/{{ registry[module].length }}</span>
                        </label>
                        <div class="grid grid-cols-2 gap-2 pl-6 sm:grid-cols-4 lg:grid-cols-6">
                            <label v-for="action in actionsFor(module)" :key="action" class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    :model-value="form.permissions.includes(permissionName(module, action))"
                                    @update:model-value="togglePermission(permissionName(module, action), $event === true)"
                                />
                                {{ action.replace(/-/g, ' ') }}
                            </label>
                        </div>
                    </div>

                    <div v-if="visibleGlobals.length">
                        <p class="mb-2 text-sm font-semibold">Global</p>
                        <div class="grid grid-cols-2 gap-2 pl-6 sm:grid-cols-4">
                            <label v-for="permission in visibleGlobals" :key="permission" class="flex items-center gap-2 text-sm">
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
