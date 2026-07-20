<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { Branch, BreadcrumbItem, Department, Employee, Team } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    branches: Pick<Branch, 'id' | 'name'>[];
    departments: Pick<Department, 'id' | 'name'>[];
    teams: Pick<Team, 'id' | 'name' | 'branch_id' | 'department_id'>[];
    roles: { id: number; name: string }[];
    managers: { id: number; name: string }[];
    employee?: Employee;
}>();

const editing = computed(() => props.employee !== undefined);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Employees', href: '/admin/employees' },
    { title: editing.value ? 'Edit Employee' : 'New Employee', href: '#' },
];

const form = useForm({
    name: props.employee?.name ?? '',
    email: props.employee?.email ?? '',
    phone: props.employee?.phone ?? '',
    password: '',
    branch_id: props.employee?.branch_id ?? (null as number | null),
    department_id: props.employee?.department_id ?? (null as number | null),
    team_id: props.employee?.team_id ?? (null as number | null),
    is_active: props.employee?.is_active ?? true,
    force_password_change: props.employee?.force_password_change ?? false,
    roles: (props.employee?.roles ?? []).map((role) => role.name),
    profile: {
        designation: props.employee?.employee_profile?.designation ?? '',
        date_of_joining: props.employee?.employee_profile?.date_of_joining?.slice(0, 10) ?? '',
        dob: props.employee?.employee_profile?.dob?.slice(0, 10) ?? '',
        gender: props.employee?.employee_profile?.gender ?? '',
        address: props.employee?.employee_profile?.address ?? '',
        city: props.employee?.employee_profile?.city ?? '',
        state: props.employee?.employee_profile?.state ?? '',
        pin_code: props.employee?.employee_profile?.pin_code ?? '',
        emergency_contact_name: props.employee?.employee_profile?.emergency_contact_name ?? '',
        emergency_contact_phone: props.employee?.employee_profile?.emergency_contact_phone ?? '',
        blood_group: props.employee?.employee_profile?.blood_group ?? '',
        id_proof_type: props.employee?.employee_profile?.id_proof_type ?? '',
        id_proof_number: props.employee?.employee_profile?.id_proof_number ?? '',
        reports_to: props.employee?.employee_profile?.reports_to ?? (null as number | null),
    },
});

const availableTeams = computed(() =>
    props.teams.filter(
        (team) =>
            (!form.branch_id || team.branch_id === form.branch_id) &&
            (!form.department_id || team.department_id === form.department_id),
    ),
);

function toggleRole(name: string, checked: boolean) {
    if (checked && !form.roles.includes(name)) {
        form.roles.push(name);
    } else if (!checked) {
        form.roles = form.roles.filter((role) => role !== name);
    }
}

function submit() {
    if (editing.value) {
        form.put(`/admin/employees/${props.employee!.id}`);
    } else {
        form.post('/admin/employees');
    }
}
</script>

<template>
    <Head :title="editing ? 'Edit Employee' : 'New Employee'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">{{ editing ? `Edit ${employee!.name}` : 'New Employee' }}</h1>
                <div class="flex gap-2">
                    <Button variant="outline" as-child><Link href="/admin/employees">Cancel</Link></Button>
                    <Button type="submit" :disabled="form.processing">{{ editing ? 'Save Changes' : 'Create Employee' }}</Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Account</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="emp-name">Full Name *</Label>
                            <Input id="emp-name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="emp-email">Email *</Label>
                                <Input id="emp-email" v-model="form.email" type="email" required />
                                <InputError :message="form.errors.email" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-phone">Phone</Label>
                                <Input id="emp-phone" v-model="form.phone" maxlength="20" />
                                <InputError :message="form.errors.phone" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="emp-password">{{ editing ? 'New Password (leave blank to keep)' : 'Password *' }}</Label>
                            <Input id="emp-password" v-model="form.password" type="password" :required="!editing" autocomplete="new-password" />
                            <InputError :message="form.errors.password" />
                        </div>
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox :model-value="form.is_active" @update:model-value="form.is_active = $event === true" />
                                Active
                            </label>
                            <label class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    :model-value="form.force_password_change"
                                    @update:model-value="form.force_password_change = $event === true"
                                />
                                Require password change on next login
                            </label>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Posting</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="emp-branch">Branch *</Label>
                                <select
                                    id="emp-branch"
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
                                <Label for="emp-department">Department *</Label>
                                <select
                                    id="emp-department"
                                    v-model="form.department_id"
                                    required
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                >
                                    <option :value="null" disabled>Select department…</option>
                                    <option v-for="department in departments" :key="department.id" :value="department.id">
                                        {{ department.name }}
                                    </option>
                                </select>
                                <InputError :message="form.errors.department_id" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="emp-team">Team</Label>
                                <select
                                    id="emp-team"
                                    v-model="form.team_id"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                >
                                    <option :value="null">None</option>
                                    <option v-for="team in availableTeams" :key="team.id" :value="team.id">{{ team.name }}</option>
                                </select>
                                <InputError :message="form.errors.team_id" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-designation">Designation</Label>
                                <Input id="emp-designation" v-model="form.profile.designation" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="emp-doj">Date of Joining</Label>
                                <Input id="emp-doj" v-model="form.profile.date_of_joining" type="date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-reports">Reports To</Label>
                                <select
                                    id="emp-reports"
                                    v-model="form.profile.reports_to"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                >
                                    <option :value="null">None</option>
                                    <option v-for="manager in managers" :key="manager.id" :value="manager.id">{{ manager.name }}</option>
                                </select>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Roles *</CardTitle></CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3">
                            <label v-for="role in roles" :key="role.id" class="flex items-center gap-2 text-sm">
                                <Checkbox
                                    :model-value="form.roles.includes(role.name)"
                                    @update:model-value="toggleRole(role.name, $event === true)"
                                />
                                {{ role.name }}
                            </label>
                        </div>
                        <InputError class="mt-2" :message="form.errors.roles" />
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Personal Details</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="grid gap-2">
                                <Label for="emp-dob">Date of Birth</Label>
                                <Input id="emp-dob" v-model="form.profile.dob" type="date" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-gender">Gender</Label>
                                <select
                                    id="emp-gender"
                                    v-model="form.profile.gender"
                                    class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-sm"
                                >
                                    <option value="">—</option>
                                    <option value="male">Male</option>
                                    <option value="female">Female</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-blood">Blood Group</Label>
                                <Input id="emp-blood" v-model="form.profile.blood_group" maxlength="10" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="emp-address">Address</Label>
                            <Input id="emp-address" v-model="form.profile.address" />
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="grid gap-2">
                                <Label for="emp-city">City</Label>
                                <Input id="emp-city" v-model="form.profile.city" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-state">State</Label>
                                <Input id="emp-state" v-model="form.profile.state" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-pin">PIN Code</Label>
                                <Input id="emp-pin" v-model="form.profile.pin_code" maxlength="10" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="emp-ec-name">Emergency Contact Name</Label>
                                <Input id="emp-ec-name" v-model="form.profile.emergency_contact_name" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-ec-phone">Emergency Contact Phone</Label>
                                <Input id="emp-ec-phone" v-model="form.profile.emergency_contact_phone" maxlength="20" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="emp-id-type">ID Proof Type</Label>
                                <Input id="emp-id-type" v-model="form.profile.id_proof_type" maxlength="50" placeholder="Aadhaar / PAN / DL" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="emp-id-number">ID Proof Number</Label>
                                <Input id="emp-id-number" v-model="form.profile.id_proof_number" maxlength="50" />
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </form>
    </AppLayout>
</template>
