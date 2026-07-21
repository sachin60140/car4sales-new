<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';

const props = defineProps<{
    branches: { id: number; name: string }[];
    statuses: { value: string; label: string }[];
    canCost: boolean;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/admin/inventory' },
    { title: 'Add Stock', href: '#' },
];

const form = useForm({
    make: '',
    model: '',
    variant: '',
    registration_number: '',
    chassis_number: '',
    engine_number: '',
    manufacturing_year: null as number | null,
    registration_state: '',
    fuel_type: '',
    transmission: '',
    body_type: '',
    color: '',
    odometer_km: null as number | null,
    ownership_serial: null as number | null,
    branch_id: null as number | null,
    status: props.statuses[0]?.value ?? 'in_stock',
    purchase_price: null as number | null,
    landed_cost: null as number | null,
    asking_price: null as number | null,
    refurb_required: false,
});

const fuels = ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'];
const transmissions = ['Manual', 'Automatic'];
const bodyTypes = ['Hatchback', 'Sedan', 'SUV', 'MUV', 'Coupe', 'Convertible', 'Pickup', 'Van'];

function submit() {
    form.post('/admin/inventory');
}

const inputClass = 'h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm shadow-sm';
</script>

<template>
    <Head title="Add Stock" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <form class="flex flex-col gap-4 p-4" @submit.prevent="submit">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Add Stock</h1>
                    <p class="text-sm text-muted-foreground">Manually add a vehicle to inventory. A stock number is allocated automatically.</p>
                </div>
                <div class="flex items-center gap-2">
                    <Button variant="outline" as-child>
                        <Link href="/admin/inventory">Cancel</Link>
                    </Button>
                    <Button type="submit" :disabled="form.processing">Add Stock</Button>
                </div>
            </div>

            <Card>
                <CardHeader><CardTitle>Vehicle</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="make">Make <span class="text-brand-red">*</span></Label>
                        <Input id="make" v-model="form.make" placeholder="e.g. Maruti Suzuki" />
                        <InputError :message="form.errors.make" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="model">Model <span class="text-brand-red">*</span></Label>
                        <Input id="model" v-model="form.model" placeholder="e.g. Swift" />
                        <InputError :message="form.errors.model" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="variant">Variant</Label>
                        <Input id="variant" v-model="form.variant" placeholder="e.g. VXI" />
                        <InputError :message="form.errors.variant" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="year">Manufacturing year</Label>
                        <Input id="year" v-model.number="form.manufacturing_year" type="number" placeholder="2020" />
                        <InputError :message="form.errors.manufacturing_year" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="fuel">Fuel type</Label>
                        <select id="fuel" v-model="form.fuel_type" :class="inputClass">
                            <option value="">Select…</option>
                            <option v-for="f in fuels" :key="f" :value="f">{{ f }}</option>
                        </select>
                        <InputError :message="form.errors.fuel_type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="transmission">Transmission</Label>
                        <select id="transmission" v-model="form.transmission" :class="inputClass">
                            <option value="">Select…</option>
                            <option v-for="t in transmissions" :key="t" :value="t">{{ t }}</option>
                        </select>
                        <InputError :message="form.errors.transmission" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="body">Body type</Label>
                        <select id="body" v-model="form.body_type" :class="inputClass">
                            <option value="">Select…</option>
                            <option v-for="b in bodyTypes" :key="b" :value="b">{{ b }}</option>
                        </select>
                        <InputError :message="form.errors.body_type" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="color">Colour</Label>
                        <Input id="color" v-model="form.color" placeholder="e.g. White" />
                        <InputError :message="form.errors.color" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="odometer">Odometer (km)</Label>
                        <Input id="odometer" v-model.number="form.odometer_km" type="number" placeholder="35000" />
                        <InputError :message="form.errors.odometer_km" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="ownership">Ownership (serial)</Label>
                        <Input id="ownership" v-model.number="form.ownership_serial" type="number" placeholder="1" />
                        <InputError :message="form.errors.ownership_serial" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Registration &amp; identity</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="reg">Registration number</Label>
                        <Input id="reg" v-model="form.registration_number" placeholder="e.g. MH12AB1234" class="uppercase" />
                        <InputError :message="form.errors.registration_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="reg_state">Registration state</Label>
                        <Input id="reg_state" v-model="form.registration_state" placeholder="e.g. Maharashtra" />
                        <InputError :message="form.errors.registration_state" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="chassis">Chassis number</Label>
                        <Input id="chassis" v-model="form.chassis_number" />
                        <InputError :message="form.errors.chassis_number" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="engine">Engine number</Label>
                        <Input id="engine" v-model="form.engine_number" />
                        <InputError :message="form.errors.engine_number" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Placement &amp; pricing</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label for="branch">Branch</Label>
                        <select id="branch" v-model="form.branch_id" :class="inputClass">
                            <option :value="null">Unassigned</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <InputError :message="form.errors.branch_id" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="status">Initial status <span class="text-brand-red">*</span></Label>
                        <select id="status" v-model="form.status" :class="inputClass">
                            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div v-if="canCost" class="grid gap-1.5">
                        <Label for="purchase">Purchase price (₹)</Label>
                        <Input id="purchase" v-model.number="form.purchase_price" type="number" step="0.01" />
                        <InputError :message="form.errors.purchase_price" />
                    </div>
                    <div v-if="canCost" class="grid gap-1.5">
                        <Label for="landed">Landed cost (₹)</Label>
                        <Input id="landed" v-model.number="form.landed_cost" type="number" step="0.01" />
                        <InputError :message="form.errors.landed_cost" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label for="asking">Asking price (₹)</Label>
                        <Input id="asking" v-model.number="form.asking_price" type="number" step="0.01" />
                        <InputError :message="form.errors.asking_price" />
                    </div>
                    <label class="flex items-center gap-2 self-end pb-2 text-sm">
                        <input v-model="form.refurb_required" type="checkbox" class="size-4 rounded border-input" />
                        Needs refurbishment
                    </label>
                </CardContent>
            </Card>

            <div class="flex justify-end gap-2">
                <Button variant="outline" as-child>
                    <Link href="/admin/inventory">Cancel</Link>
                </Button>
                <Button type="submit" :disabled="form.processing">Add Stock</Button>
            </div>
        </form>
    </AppLayout>
</template>
