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

defineProps<{
    branches: { id: number; name: string }[];
    sources: { value: string; label: string }[];
    telecallers: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales Leads', href: '/admin/sales-leads' },
    { title: 'New Lead', href: '#' },
];

const form = useForm({
    name: '',
    mobile: '',
    email: '',
    city: '',
    budget_min: null as number | null,
    budget_max: null as number | null,
    source: 'walk_in',
    branch_id: null as number | null,
    telecaller_id: null as number | null,
    finance_required: false as boolean,
    exchange_required: false as boolean,
    priority: 'normal',
    remarks: '',
});

function submit() {
    form.post('/admin/sales-leads');
}
</script>

<template>
    <Head title="New Sales Lead" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">New Sales Lead</h1>
                <div class="flex gap-2">
                    <Button variant="outline" as-child><Link href="/admin/sales-leads">Cancel</Link></Button>
                    <Button type="submit" :disabled="form.processing">Create Lead</Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Customer</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="name">Name *</Label>
                            <Input id="name" v-model="form.name" required />
                            <InputError :message="form.errors.name" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="mobile">Mobile *</Label>
                                <Input id="mobile" v-model="form.mobile" required maxlength="20" />
                                <InputError :message="form.errors.mobile" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="city">City</Label>
                            <Input id="city" v-model="form.city" />
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Requirement</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="bmin">Budget Min (₹)</Label>
                                <Input id="bmin" v-model.number="form.budget_min" type="number" min="0" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="bmax">Budget Max (₹)</Label>
                                <Input id="bmax" v-model.number="form.budget_max" type="number" min="0" />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-6">
                            <label class="flex items-center gap-2 text-sm"><Checkbox :model-value="form.finance_required" @update:model-value="form.finance_required = $event === true" /> Finance required</label>
                            <label class="flex items-center gap-2 text-sm"><Checkbox :model-value="form.exchange_required" @update:model-value="form.exchange_required = $event === true" /> Exchange required</label>
                        </div>
                        <div class="grid gap-2">
                            <Label for="priority">Priority</Label>
                            <select id="priority" v-model="form.priority" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="hot">Hot</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Source &amp; Assignment</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="source">Source *</Label>
                                <select id="source" v-model="form.source" required class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option v-for="s in sources" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="branch">Branch</Label>
                                <select id="branch" v-model="form.branch_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option :value="null">—</option>
                                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="telecaller">Assign Telecaller</Label>
                            <select id="telecaller" v-model="form.telecaller_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option :value="null">Unassigned</option>
                                <option v-for="t in telecallers" :key="t.id" :value="t.id">{{ t.name }}</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Remarks</CardTitle></CardHeader>
                    <CardContent>
                        <textarea v-model="form.remarks" rows="4" class="w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm" placeholder="Notes about this lead…" />
                    </CardContent>
                </Card>
            </div>
        </form>
    </AppLayout>
</template>
