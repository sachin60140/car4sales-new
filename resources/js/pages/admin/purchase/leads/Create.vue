<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

defineProps<{
    branches: { id: number; name: string }[];
    employees: { id: number; name: string }[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Purchase Leads', href: '/admin/purchase-leads' },
    { title: 'New Lead', href: '#' },
];

const form = useForm({
    seller_name: '',
    seller_type: 'individual',
    mobile: '',
    alt_mobile: '',
    email: '',
    city: '',
    pin_code: '',
    source: 'manual',
    registration_number: '',
    make: '',
    model: '',
    variant: '',
    manufacturing_year: null as number | null,
    fuel_type: '',
    transmission: '',
    odometer_km: null as number | null,
    expected_price: null as number | null,
    loan_status: 'none',
    inspection_location: '',
    assigned_to: null as number | null,
    branch_id: null as number | null,
    priority: 'normal',
    next_follow_up_at: '',
    remarks: '',
});

function submit() {
    form.post('/admin/purchase-leads');
}
</script>

<template>
    <Head title="New Purchase Lead" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex items-center justify-between">
                <h1 class="text-xl font-semibold">New Purchase Lead</h1>
                <div class="flex gap-2">
                    <Button variant="outline" as-child><Link href="/admin/purchase-leads">Cancel</Link></Button>
                    <Button type="submit" :disabled="form.processing">Create Lead</Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Seller</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid gap-2">
                            <Label for="seller_name">Seller Name *</Label>
                            <Input id="seller_name" v-model="form.seller_name" required />
                            <InputError :message="form.errors.seller_name" />
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="mobile">Mobile *</Label>
                                <Input id="mobile" v-model="form.mobile" required maxlength="20" />
                                <InputError :message="form.errors.mobile" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="alt_mobile">Alt. Mobile</Label>
                                <Input id="alt_mobile" v-model="form.alt_mobile" maxlength="20" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="seller_type">Seller Type</Label>
                                <select id="seller_type" v-model="form.seller_type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option value="individual">Individual</option>
                                    <option value="dealer">Dealer</option>
                                    <option value="company">Company</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="city">City</Label>
                                <Input id="city" v-model="form.city" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="pin_code">PIN Code</Label>
                                <Input id="pin_code" v-model="form.pin_code" maxlength="10" />
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Vehicle</CardTitle></CardHeader>
                    <CardContent class="grid gap-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="registration_number">Registration No.</Label>
                                <Input id="registration_number" v-model="form.registration_number" maxlength="20" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="manufacturing_year">Mfg. Year</Label>
                                <Input id="manufacturing_year" v-model.number="form.manufacturing_year" type="number" min="1980" :max="new Date().getFullYear() + 1" />
                                <InputError :message="form.errors.manufacturing_year" />
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-4">
                            <div class="grid gap-2">
                                <Label for="make">Make</Label>
                                <Input id="make" v-model="form.make" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="model">Model</Label>
                                <Input id="model" v-model="form.model" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="variant">Variant</Label>
                                <Input id="variant" v-model="form.variant" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="fuel_type">Fuel</Label>
                                <select id="fuel_type" v-model="form.fuel_type" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option value="">—</option>
                                    <option>Petrol</option><option>Diesel</option><option>CNG</option><option>Electric</option><option>Hybrid</option>
                                </select>
                            </div>
                            <div class="grid gap-2">
                                <Label for="transmission">Transmission</Label>
                                <select id="transmission" v-model="form.transmission" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option value="">—</option>
                                    <option>Manual</option><option>Automatic</option>
                                </select>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div class="grid gap-2">
                                <Label for="odometer_km">Odometer (km)</Label>
                                <Input id="odometer_km" v-model.number="form.odometer_km" type="number" min="0" />
                            </div>
                            <div class="grid gap-2">
                                <Label for="expected_price">Seller Expected (₹)</Label>
                                <Input id="expected_price" v-model.number="form.expected_price" type="number" min="0" />
                            </div>
                        </div>
                        <div class="grid gap-2">
                            <Label for="loan_status">Loan Status</Label>
                            <select id="loan_status" v-model="form.loan_status" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="none">No loan</option>
                                <option value="active">Active loan (hypothecation)</option>
                                <option value="closed_pending_noc">Closed, NOC pending</option>
                            </select>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle>Assignment</CardTitle></CardHeader>
                    <CardContent class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="branch_id">Branch</Label>
                            <select id="branch_id" v-model="form.branch_id" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option :value="null">Auto (my branch)</option>
                                <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="assigned_to">Assign To</Label>
                            <select id="assigned_to" v-model="form.assigned_to" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option :value="null">Unassigned</option>
                                <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="priority">Priority</Label>
                            <select id="priority" v-model="form.priority" class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="low">Low</option><option value="normal">Normal</option><option value="high">High</option><option value="hot">Hot</option>
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="next_follow_up_at">Next Follow-up</Label>
                            <Input id="next_follow_up_at" v-model="form.next_follow_up_at" type="datetime-local" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="inspection_location">Inspection Location</Label>
                            <Input id="inspection_location" v-model="form.inspection_location" />
                        </div>
                        <div class="grid gap-2 sm:col-span-2">
                            <Label for="remarks">Remarks</Label>
                            <Input id="remarks" v-model="form.remarks" />
                        </div>
                    </CardContent>
                </Card>
            </div>
        </form>
    </AppLayout>
</template>
