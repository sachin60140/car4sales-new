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

interface Partner {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    company_name: string | null;
    contact_person: string | null;
    city: string | null;
    gst_number: string | null;
    status: string;
    status_label: string;
}

const props = defineProps<{
    partner: Partner | null;
    statuses: { value: string; label: string }[];
}>();

const isEdit = computed(() => props.partner !== null);

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendor Partners', href: '/admin/vendor-partners' },
    { title: isEdit.value ? 'Edit Partner' : 'Add Partner', href: '#' },
];

const form = useForm({
    name: props.partner?.name ?? '',
    email: props.partner?.email ?? '',
    password: '',
    phone: props.partner?.phone ?? '',
    company_name: props.partner?.company_name ?? '',
    contact_person: props.partner?.contact_person ?? '',
    city: props.partner?.city ?? '',
    gst_number: props.partner?.gst_number ?? '',
    status: props.partner?.status ?? 'active',
});

function submit() {
    if (isEdit.value) {
        form.patch(`/admin/vendor-partners/${props.partner!.id}`);
    } else {
        form.post('/admin/vendor-partners');
    }
}

const inputClass = 'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm';
</script>

<template>
    <Head :title="isEdit ? 'Edit Vendor Partner' : 'Add Vendor Partner'" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ isEdit ? 'Edit Vendor Partner' : 'Add Vendor Partner' }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            isEdit
                                ? 'Update the partner’s login and profile details.'
                                : 'Create a sourcing partner who can log in and submit vehicles.'
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/admin/vendor-partners">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Add Partner' }}</Button>
                </div>
            </div>

            <Card>
                <CardHeader><CardTitle>Login</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="name">Contact name <span class="text-brand-red">*</span></Label>
                        <Input id="name" v-model="form.name" placeholder="Full name" />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="email">Email <span class="text-brand-red">*</span></Label>
                        <Input id="email" v-model="form.email" type="email" placeholder="partner@example.com" />
                        <InputError :message="form.errors.email" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="phone">Phone</Label>
                        <Input id="phone" v-model="form.phone" placeholder="Mobile number" />
                        <InputError :message="form.errors.phone" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="password">
                            {{ isEdit ? 'New password' : 'Password' }}<span v-if="!isEdit" class="text-brand-red"> *</span>
                        </Label>
                        <Input id="password" v-model="form.password" type="password" :placeholder="isEdit ? 'Leave blank to keep current' : ''" />
                        <InputError :message="form.errors.password" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Business profile</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="company_name">Company name</Label>
                        <Input id="company_name" v-model="form.company_name" placeholder="Dealership / firm" />
                        <InputError :message="form.errors.company_name" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="contact_person">Contact person</Label>
                        <Input id="contact_person" v-model="form.contact_person" placeholder="Defaults to contact name" />
                        <InputError :message="form.errors.contact_person" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="city">City</Label>
                        <Input id="city" v-model="form.city" />
                        <InputError :message="form.errors.city" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="gst_number">GST number</Label>
                        <Input id="gst_number" v-model="form.gst_number" class="uppercase" />
                        <InputError :message="form.errors.gst_number" />
                    </div>
                    <div v-if="!isEdit" class="grid gap-1.5">
                        <Label for="status">Initial status <span class="text-brand-red">*</span></Label>
                        <select id="status" v-model="form.status" :class="inputClass">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                </CardContent>
            </Card>

            <p v-if="isEdit" class="text-xs text-muted-foreground">
                Current status: <strong>{{ partner?.status_label }}</strong> — change it with the Activate / Suspend actions on the partners list.
            </p>

            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/admin/vendor-partners">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">{{ isEdit ? 'Save Changes' : 'Add Partner' }}</Button>
            </div>
        </form>
    </AppLayout>
</template>
