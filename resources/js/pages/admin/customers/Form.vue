<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Customer {
    id: number;
    customer_code: string;
    name: string;
    father_name: string | null;
    mobile: string;
    alt_mobile: string | null;
    email: string | null;
    address: string | null;
    city: string | null;
    state: string | null;
    pin_code: string | null;
    occupation: string | null;
    dob: string | null;
    aadhaar_number?: string | null;
    pan_number?: string | null;
    branch_id: number | null;
}

const props = defineProps<{
    customer: Customer | null;
    branches: { id: number; name: string }[];
    canViewKyc: boolean;
}>();

const isEdit = computed(() => props.customer !== null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/admin/customers' },
    { title: isEdit.value ? 'Edit Customer' : 'Add Customer', href: '#' },
];

const form = useForm({
    name: props.customer?.name ?? '',
    father_name: props.customer?.father_name ?? '',
    mobile: props.customer?.mobile ?? '',
    alt_mobile: props.customer?.alt_mobile ?? '',
    email: props.customer?.email ?? '',
    address: props.customer?.address ?? '',
    city: props.customer?.city ?? '',
    state: props.customer?.state ?? '',
    pin_code: props.customer?.pin_code ?? '',
    occupation: props.customer?.occupation ?? '',
    dob: props.customer?.dob ? String(props.customer.dob).slice(0, 10) : '',
    aadhaar_number: props.customer?.aadhaar_number ?? '',
    pan_number: props.customer?.pan_number ?? '',
    branch_id: props.customer?.branch_id ?? null,
});

function submit() {
    if (isEdit.value) {
        form.patch(`/admin/customers/${props.customer!.id}`);
    } else {
        form.post('/admin/customers');
    }
}

const inputClass = 'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm';
</script>

<template>
    <Head :title="isEdit ? 'Edit Customer' : 'Add Customer'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ isEdit ? 'Edit Customer' : 'Add Customer' }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            isEdit
                                ? 'Update this customer’s contact and profile details.'
                                : 'Create a customer record. One record is kept per mobile number.'
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/admin/customers">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Add Customer' }}</Button>
                </div>
            </div>

            <Card>
                <CardHeader><CardTitle>Contact</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="name">Name <span class="text-brand-red">*</span></Label>
                        <Input id="name" v-model="form.name" placeholder="Full name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="father_name">Father's name</Label>
                        <Input id="father_name" v-model="form.father_name" placeholder="Father's name" />
                        <InputError :message="form.errors.father_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="mobile">Mobile <span class="text-brand-red">*</span></Label>
                        <Input id="mobile" v-model="form.mobile" placeholder="10-digit mobile" />
                        <InputError :message="form.errors.mobile" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="alt_mobile">Alternate mobile</Label>
                        <Input id="alt_mobile" v-model="form.alt_mobile" />
                        <InputError :message="form.errors.alt_mobile" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="email">Email</Label>
                        <Input id="email" v-model="form.email" type="email" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="occupation">Occupation</Label>
                        <Input id="occupation" v-model="form.occupation" />
                        <InputError :message="form.errors.occupation" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="dob">Date of birth</Label>
                        <Input id="dob" v-model="form.dob" type="date" />
                        <InputError :message="form.errors.dob" />
                    </div>
                </CardContent>
            </Card>

            <Card v-if="canViewKyc">
                <CardHeader>
                    <CardTitle>Identity (KYC)</CardTitle>
                    <p class="mt-1 text-sm text-muted-foreground">Sensitive — visible only to staff with KYC access.</p>
                </CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="aadhaar_number">Aadhaar number</Label>
                        <Input id="aadhaar_number" v-model="form.aadhaar_number" inputmode="numeric" placeholder="12-digit number" maxlength="12" />
                        <InputError :message="form.errors.aadhaar_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pan_number">PAN number</Label>
                        <Input id="pan_number" v-model="form.pan_number" class="uppercase" placeholder="ABCDE1234F" maxlength="10" />
                        <InputError :message="form.errors.pan_number" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Address &amp; branch</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5 sm:col-span-2 lg:col-span-3">
                        <Label for="address">Address</Label>
                        <Input id="address" v-model="form.address" />
                        <InputError :message="form.errors.address" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="city">City</Label>
                        <Input id="city" v-model="form.city" />
                        <InputError :message="form.errors.city" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="state">State</Label>
                        <Input id="state" v-model="form.state" />
                        <InputError :message="form.errors.state" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="pin_code">PIN code</Label>
                        <Input id="pin_code" v-model="form.pin_code" />
                        <InputError :message="form.errors.pin_code" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="branch_id">Branch</Label>
                        <select id="branch_id" v-model="form.branch_id" :class="inputClass">
                            <option :value="null">Unassigned</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <InputError :message="form.errors.branch_id" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/admin/customers">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Add Customer' }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
