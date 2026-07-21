<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    vehicle: Record<string, any>;
    branches: { id: number; name: string }[];
    vendors: { id: number; name: string; type: string }[];
    employees: { id: number; name: string }[];
    acquisitionSources: { value: string; label: string }[];
    expenseCategories: { value: string; label: string }[];
    movementTypes: { value: string; label: string }[];
    statuses: { value: string; label: string }[];
    allowedTransitions: { value: string; label: string }[];
    can: Record<string, boolean>;
}>();

const v = computed(() => props.vehicle);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inventory', href: '/admin/inventory' },
    { title: v.value.stock_number, href: '#' },
];

const tabs = computed(() => {
    const base = ['Overview', 'Media', 'Documents', 'Movements', 'Refurbishment', 'Publish'];
    if (props.can.viewCost) base.splice(3, 0, 'Expenses', 'Pricing');
    return base;
});
const activeTab = ref('Overview');

function money(x: unknown): string {
    if (x === null || x === undefined || x === '') return '—';
    return '₹' + Number(x).toLocaleString('en-IN');
}
function post(url: string, data: Record<string, string | number | boolean | null> = {}, done?: () => void) {
    router.post(url, data, { preserveScroll: true, onSuccess: done });
}

// Source & purchase (acquisition)
const editingSource = ref(false);
const sourceForm = useForm({
    acquisition_source: props.vehicle.acquisition_source ?? '',
    seller_name: props.vehicle.seller_name ?? '',
    seller_contact: props.vehicle.seller_contact ?? '',
    purchased_by: props.vehicle.purchased_by ?? null,
    purchased_at: props.vehicle.purchased_at ? String(props.vehicle.purchased_at).slice(0, 10) : '',
    purchase_reference: props.vehicle.purchase_reference ?? '',
});
function saveSource() {
    sourceForm.patch(`/admin/inventory/${v.value.id}`, { preserveScroll: true, onSuccess: () => (editingSource.value = false) });
}
const sourceLabel = computed(() => props.acquisitionSources.find((s) => s.value === v.value.acquisition_source)?.label ?? '—');
function fmtDate(x: unknown): string {
    if (!x) return '—';
    return new Date(x as string).toLocaleDateString('en-IN');
}

// Media
const mediaForm = useForm<{ file: File | null; category: string; is_public: boolean }>({ file: null, category: '', is_public: false });
function uploadMedia() {
    mediaForm.post(`/admin/inventory/${v.value.id}/media`, { preserveScroll: true, forceFormData: true, onSuccess: () => mediaForm.reset() });
}
function deleteMedia(id: number) {
    router.delete(`/admin/inventory/${v.value.id}/media/${id}`, { preserveScroll: true });
}

// Documents
const docForm = useForm<{ type: string; file: File | null; number: string; valid_till: string }>({
    type: 'rc',
    file: null,
    number: '',
    valid_till: '',
});
function uploadDoc() {
    docForm.post(`/admin/inventory/${v.value.id}/documents`, { preserveScroll: true, forceFormData: true, onSuccess: () => docForm.reset() });
}

// Expenses
const expenseForm = useForm({ category: 'refurbishment', amount: 0, description: '', vendor_id: null as number | null });
function addExpense() {
    expenseForm.post(`/admin/inventory/${v.value.id}/expenses`, { preserveScroll: true, onSuccess: () => expenseForm.reset() });
}
function approveExpense(id: number) {
    post(`/admin/vehicle-expenses/${id}/approve`);
}
function reverseExpense(id: number) {
    const remarks = prompt('Reason for reversal?');
    if (remarks) post(`/admin/vehicle-expenses/${id}/reverse`, { remarks });
}

// Pricing
const priceForm = useForm({ price_type: 'asking', new_price: 0, reason: '' });
function updatePrice() {
    priceForm.post(`/admin/inventory/${v.value.id}/price`, { preserveScroll: true, onSuccess: () => priceForm.reset() });
}

// Transfer & movement
const transferForm = useForm({ to_branch_id: null as number | null, parking_location: '', remarks: '' });
function transfer() {
    transferForm.post(`/admin/inventory/${v.value.id}/transfer`, { preserveScroll: true, onSuccess: () => transferForm.reset() });
}
const moveForm = useForm({ type: 'workshop', to_location: '', reference: '', expected_return_at: '', remarks: '' });
function recordMove() {
    moveForm.post(`/admin/inventory/${v.value.id}/move`, { preserveScroll: true, onSuccess: () => moveForm.reset() });
}

// Publish
const publishForm = useForm({ web: true, mobile: true });
function publish() {
    publishForm.post(`/admin/inventory/${v.value.id}/publish`, { preserveScroll: true });
}
function unpublish() {
    post(`/admin/inventory/${v.value.id}/unpublish`);
}

// Workshop job creation
const jobForm = useForm({
    type: 'internal',
    vendor_id: null as number | null,
    description: '',
    expected_completion: '',
    items: [{ description: '', defect: '', work_type: 'labour', estimate: 0 }],
});
function addJobItem() {
    jobForm.items.push({ description: '', defect: '', work_type: 'labour', estimate: 0 });
}
function removeJobItem(i: number) {
    jobForm.items.splice(i, 1);
}
function createJob() {
    router.post('/admin/workshop', { ...jobForm.data(), vehicle_id: v.value.id }, { preserveScroll: true });
}
</script>

<template>
    <Head :title="vehicle.stock_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ vehicle.stock_number }}</h1>
                        <span class="rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-xs font-medium text-brand-maroon dark:text-brand-yellow">
                            {{ statuses.find((s) => s.value === vehicle.status)?.label ?? vehicle.status }}
                        </span>
                        <span
                            v-if="vehicle.published_web"
                            class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400"
                            >Live on website</span
                        >
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ [vehicle.make, vehicle.model, vehicle.variant].filter(Boolean).join(' ') }} · {{ vehicle.manufacturing_year ?? '' }} ·
                        {{ vehicle.registration_number ?? 'Unregistered' }} ·
                        {{ vehicle.branch?.name ?? '' }}
                    </p>
                </div>
                <Button variant="outline" as-child><Link href="/admin/inventory">Back to stock</Link></Button>
            </div>

            <!-- Quick stats -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card
                    ><CardContent class="p-4"
                        ><p class="text-xs text-muted-foreground">Asking Price</p>
                        <p class="text-lg font-bold">{{ money(vehicle.asking_price) }}</p></CardContent
                    ></Card
                >
                <Card v-if="can.viewCost"
                    ><CardContent class="p-4"
                        ><p class="text-xs text-muted-foreground">Landed Cost</p>
                        <p class="text-lg font-bold">{{ money(vehicle.landed_cost) }}</p></CardContent
                    ></Card
                >
                <Card v-if="can.viewProfit && vehicle.asking_price"
                    ><CardContent class="p-4"
                        ><p class="text-xs text-muted-foreground">Est. Gross Margin</p>
                        <p class="text-lg font-bold">{{ money(Number(vehicle.asking_price) - Number(vehicle.landed_cost ?? 0)) }}</p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="p-4"
                        ><p class="text-xs text-muted-foreground">Odometer</p>
                        <p class="text-lg font-bold">
                            {{ vehicle.odometer_km ? Number(vehicle.odometer_km).toLocaleString('en-IN') + ' km' : '—' }}
                        </p></CardContent
                    ></Card
                >
            </div>

            <!-- Tabs -->
            <div class="flex flex-wrap gap-1 border-b">
                <button
                    v-for="tab in tabs"
                    :key="tab"
                    class="border-b-2 px-4 py-2 text-sm font-medium transition-colors"
                    :class="activeTab === tab ? 'border-primary text-foreground' : 'border-transparent text-muted-foreground hover:text-foreground'"
                    @click="activeTab = tab"
                >
                    {{ tab }}
                </button>
            </div>

            <!-- Overview -->
            <div v-show="activeTab === 'Overview'" class="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader><CardTitle>Vehicle Details</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                        <span class="text-muted-foreground">Chassis No.</span><span>{{ vehicle.chassis_number ?? '—' }}</span>
                        <span class="text-muted-foreground">Engine No.</span><span>{{ vehicle.engine_number ?? '—' }}</span>
                        <span class="text-muted-foreground">Fuel / Transmission</span
                        ><span>{{ vehicle.fuel_type ?? '—' }} / {{ vehicle.transmission ?? '—' }}</span>
                        <span class="text-muted-foreground">Colour</span><span>{{ vehicle.color ?? '—' }}</span>
                        <span class="text-muted-foreground">Ownership</span><span>{{ vehicle.ownership_serial ?? '—' }}</span>
                        <span class="text-muted-foreground">Inspection Grade</span><span>{{ vehicle.inspection_grade ?? '—' }}</span>
                        <span class="text-muted-foreground">Parking</span><span>{{ vehicle.parking_location ?? '—' }}</span>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader><CardTitle>Status History</CardTitle></CardHeader>
                    <CardContent>
                        <ol class="space-y-2 text-sm">
                            <li
                                v-for="h in vehicle.status_histories"
                                :key="h.id"
                                class="flex items-center justify-between border-b pb-2 last:border-0"
                            >
                                <span
                                    >{{ (h.to_status ?? '').replace(/_/g, ' ')
                                    }}<span v-if="h.remarks" class="text-muted-foreground"> — {{ h.remarks }}</span></span
                                >
                                <span class="text-xs text-muted-foreground"
                                    >{{ h.changer?.name ?? 'System' }} · {{ new Date(h.created_at).toLocaleDateString() }}</span
                                >
                            </li>
                        </ol>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0">
                        <CardTitle>Source &amp; Purchase</CardTitle>
                        <Button v-if="can.update && !editingSource" size="sm" variant="outline" @click="editingSource = true">Edit</Button>
                    </CardHeader>
                    <CardContent>
                        <!-- Read view -->
                        <div v-if="!editingSource" class="grid gap-y-2 text-sm sm:grid-cols-2">
                            <div class="grid grid-cols-2 gap-y-2 pr-6">
                                <span class="text-muted-foreground">Acquired from</span><span>{{ sourceLabel }}</span>
                                <span class="text-muted-foreground">Seller / vendor</span><span>{{ vehicle.seller_name ?? '—' }}</span>
                                <span class="text-muted-foreground">Contact</span><span>{{ vehicle.seller_contact ?? '—' }}</span>
                            </div>
                            <div class="grid grid-cols-2 gap-y-2 pr-6">
                                <span class="text-muted-foreground">Purchased by</span><span>{{ vehicle.purchaser?.name ?? '—' }}</span>
                                <span class="text-muted-foreground">Purchase date</span><span>{{ fmtDate(vehicle.purchased_at) }}</span>
                                <span class="text-muted-foreground">Reference</span><span>{{ vehicle.purchase_reference ?? '—' }}</span>
                            </div>
                        </div>

                        <!-- Edit view -->
                        <div v-else class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            <label class="grid gap-1 text-sm">
                                <span class="text-muted-foreground">Acquired from</span>
                                <select
                                    v-model="sourceForm.acquisition_source"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                >
                                    <option value="">Select…</option>
                                    <option v-for="s in acquisitionSources" :key="s.value" :value="s.value">{{ s.label }}</option>
                                </select>
                            </label>
                            <label class="grid gap-1 text-sm">
                                <span class="text-muted-foreground">Seller / vendor name</span>
                                <Input v-model="sourceForm.seller_name" class="h-9" />
                            </label>
                            <label class="grid gap-1 text-sm">
                                <span class="text-muted-foreground">Seller contact</span>
                                <Input v-model="sourceForm.seller_contact" class="h-9" />
                            </label>
                            <label class="grid gap-1 text-sm">
                                <span class="text-muted-foreground">Purchased by</span>
                                <select
                                    v-model="sourceForm.purchased_by"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                >
                                    <option :value="null">Select employee…</option>
                                    <option v-for="e in employees" :key="e.id" :value="e.id">{{ e.name }}</option>
                                </select>
                            </label>
                            <label class="grid gap-1 text-sm">
                                <span class="text-muted-foreground">Purchase date</span>
                                <Input v-model="sourceForm.purchased_at" type="date" class="h-9" />
                            </label>
                            <label class="grid gap-1 text-sm">
                                <span class="text-muted-foreground">Purchase reference</span>
                                <Input v-model="sourceForm.purchase_reference" class="h-9" />
                            </label>
                            <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-3">
                                <Button size="sm" :disabled="sourceForm.processing" @click="saveSource">Save</Button>
                                <Button size="sm" variant="ghost" @click="editingSource = false">Cancel</Button>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Media -->
            <Card v-show="activeTab === 'Media'">
                <CardHeader><CardTitle>Photos &amp; Videos</CardTitle></CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="can.update" class="flex flex-wrap items-end gap-2">
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.mp4,.mov"
                            class="text-sm"
                            @input="mediaForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <Input v-model="mediaForm.category" placeholder="Category" class="h-9 w-40" />
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" v-model="mediaForm.is_public" /> Public</label>
                        <Button size="sm" :disabled="!mediaForm.file || mediaForm.processing" @click="uploadMedia">Upload</Button>
                    </div>
                    <p v-if="!vehicle.media?.length" class="py-4 text-center text-sm text-muted-foreground">No media uploaded.</p>
                    <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div v-for="m in vehicle.media" :key="m.id" class="group relative overflow-hidden rounded-lg border">
                            <img
                                :src="`/admin/files/${encodeURIComponent(m.thumbnail_path ?? m.file_path)}`"
                                :alt="m.category ?? 'Vehicle'"
                                class="aspect-video w-full object-cover"
                            />
                            <div class="flex items-center justify-between px-2 py-1 text-xs">
                                <span class="capitalize">{{ m.category ?? m.type }}</span>
                                <span class="flex items-center gap-1">
                                    <span
                                        v-if="m.is_public"
                                        class="rounded bg-emerald-100 px-1 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400"
                                        >Public</span
                                    >
                                    <button v-if="can.update" class="text-brand-red" @click="deleteMedia(m.id)">✕</button>
                                </span>
                            </div>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Documents -->
            <Card v-show="activeTab === 'Documents'">
                <CardHeader><CardTitle>Vehicle Documents</CardTitle></CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="can.update" class="flex flex-wrap items-end gap-2">
                        <select v-model="docForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option value="rc">RC</option>
                            <option value="insurance">Insurance</option>
                            <option value="puc">PUC</option>
                            <option value="tax">Tax</option>
                            <option value="fitness">Fitness</option>
                            <option value="noc">NOC</option>
                            <option value="invoice">Invoice</option>
                            <option value="other">Other</option>
                        </select>
                        <Input v-model="docForm.number" placeholder="Number" class="h-9 w-40" />
                        <Input v-model="docForm.valid_till" type="date" class="h-9" />
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.pdf"
                            class="text-sm"
                            @input="docForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <Button size="sm" :disabled="!docForm.file || docForm.processing" @click="uploadDoc">Upload</Button>
                    </div>
                    <ul v-if="vehicle.documents?.length" class="divide-y text-sm">
                        <li v-for="d in vehicle.documents" :key="d.id" class="flex items-center justify-between gap-3 py-2">
                            <span class="capitalize"
                                >{{ d.type.replace(/_/g, ' ') }} <span v-if="d.number" class="text-muted-foreground">· {{ d.number }}</span></span
                            >
                            <span class="flex items-center gap-3">
                                <span class="text-xs capitalize" :class="d.status === 'verified' ? 'text-emerald-600' : 'text-muted-foreground'">{{
                                    d.valid_till ? 'valid till ' + d.valid_till : (d.status || '').replace(/_/g, ' ')
                                }}</span>
                                <a
                                    v-if="d.file_path"
                                    :href="`/admin/files/${encodeURIComponent(d.file_path)}`"
                                    target="_blank"
                                    class="text-xs font-medium underline"
                                    >View</a
                                >
                            </span>
                        </li>
                    </ul>
                    <p v-else class="py-4 text-center text-sm text-muted-foreground">No documents.</p>
                </CardContent>
            </Card>

            <!-- Expenses -->
            <Card v-show="activeTab === 'Expenses' && can.viewCost">
                <CardHeader><CardTitle>Expense Ledger</CardTitle></CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="can.manageExpenses" class="flex flex-wrap items-end gap-2">
                        <select v-model="expenseForm.category" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option v-for="c in expenseCategories" :key="c.value" :value="c.value">{{ c.label }}</option>
                        </select>
                        <Input v-model.number="expenseForm.amount" type="number" min="0" placeholder="Amount" class="h-9 w-32" />
                        <Input v-model="expenseForm.description" placeholder="Description" class="h-9" />
                        <select v-model="expenseForm.vendor_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option :value="null">No vendor</option>
                            <option v-for="ven in vendors" :key="ven.id" :value="ven.id">{{ ven.name }}</option>
                        </select>
                        <Button size="sm" :disabled="expenseForm.processing || expenseForm.amount <= 0" @click="addExpense">Add</Button>
                    </div>
                    <p class="text-xs text-muted-foreground">Approved expenses are added to the vehicle's landed cost.</p>
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-muted-foreground">
                                <th class="py-2 pr-3 font-medium">Expense #</th>
                                <th class="py-2 pr-3 font-medium">Category</th>
                                <th class="py-2 pr-3 font-medium">Amount</th>
                                <th class="py-2 pr-3 font-medium">Status</th>
                                <th class="py-2 text-right font-medium">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-if="!vehicle.expenses?.length">
                                <td colspan="5" class="py-4 text-center text-muted-foreground">No expenses.</td>
                            </tr>
                            <tr v-for="e in vehicle.expenses" :key="e.id" class="border-b last:border-0">
                                <td class="py-2 pr-3 font-mono text-xs">{{ e.expense_number }}</td>
                                <td class="py-2 pr-3 capitalize">{{ e.category }}</td>
                                <td class="py-2 pr-3">{{ money(e.amount) }}</td>
                                <td class="py-2 pr-3">
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{ e.status }}</span>
                                </td>
                                <td class="py-2 text-right">
                                    <Button
                                        v-if="can.approveExpenses && e.status === 'pending'"
                                        size="sm"
                                        variant="outline"
                                        @click="approveExpense(e.id)"
                                        >Approve</Button
                                    >
                                    <Button
                                        v-else-if="can.reverseExpenses && e.status === 'approved'"
                                        size="sm"
                                        variant="ghost"
                                        @click="reverseExpense(e.id)"
                                        >Reverse</Button
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </CardContent>
            </Card>

            <!-- Pricing -->
            <Card v-show="activeTab === 'Pricing' && can.viewCost">
                <CardHeader><CardTitle>Pricing</CardTitle></CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="can.update" class="flex flex-wrap items-end gap-2">
                        <select v-model="priceForm.price_type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option value="asking">Asking Price</option>
                            <option value="minimum">Minimum Selling Price</option>
                        </select>
                        <Input v-model.number="priceForm.new_price" type="number" min="0" placeholder="New price" class="h-9 w-36" />
                        <Input v-model="priceForm.reason" placeholder="Reason" class="h-9" />
                        <Button size="sm" :disabled="priceForm.processing" @click="updatePrice">Update</Button>
                    </div>
                    <ul v-if="vehicle.price_history?.length" class="divide-y text-sm">
                        <li v-for="p in vehicle.price_history" :key="p.id" class="flex items-center justify-between py-2">
                            <span class="capitalize"
                                >{{ p.price_type }}: {{ money(p.old_price) }} → <strong>{{ money(p.new_price) }}</strong></span
                            >
                            <span class="text-xs text-muted-foreground"
                                >{{ p.changer?.name }} · {{ new Date(p.created_at).toLocaleDateString() }}</span
                            >
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">No price changes yet.</p>
                </CardContent>
            </Card>

            <!-- Movements -->
            <Card v-show="activeTab === 'Movements'">
                <CardHeader><CardTitle>Movements &amp; Transfers</CardTitle></CardHeader>
                <CardContent class="grid gap-4 lg:grid-cols-2">
                    <div v-if="can.transfer" class="grid gap-2 rounded-lg border p-3">
                        <p class="text-sm font-medium">Branch Transfer</p>
                        <select v-model="transferForm.to_branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option :value="null">Select branch…</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <Input v-model="transferForm.parking_location" placeholder="New parking location" class="h-9" />
                        <Button size="sm" :disabled="!transferForm.to_branch_id || transferForm.processing" @click="transfer">Transfer</Button>
                    </div>
                    <div v-if="can.transfer" class="grid gap-2 rounded-lg border p-3">
                        <p class="text-sm font-medium">Record Movement</p>
                        <select v-model="moveForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option v-for="t in movementTypes.filter((m) => m.value !== 'branch_transfer')" :key="t.value" :value="t.value">
                                {{ t.label }}
                            </option>
                        </select>
                        <Input v-model="moveForm.reference" placeholder="Reference (agent, workshop…)" class="h-9" />
                        <Button size="sm" :disabled="moveForm.processing" @click="recordMove">Record</Button>
                    </div>
                    <div class="lg:col-span-2">
                        <p v-if="!vehicle.movements?.length" class="py-2 text-center text-sm text-muted-foreground">No movements recorded.</p>
                        <ul v-else class="divide-y text-sm">
                            <li v-for="mv in vehicle.movements" :key="mv.id" class="flex items-center justify-between py-2">
                                <span class="capitalize">
                                    {{ (mv.type ?? '').replace(/_/g, ' ') }}
                                    <span v-if="mv.from_branch || mv.to_branch" class="text-muted-foreground">
                                        {{ mv.from_branch?.name }} → {{ mv.to_branch?.name }}
                                    </span>
                                    <span v-if="mv.reference" class="text-muted-foreground">· {{ mv.reference }}</span>
                                </span>
                                <span class="text-xs text-muted-foreground"
                                    >{{ mv.mover?.name }} · {{ mv.moved_at ? new Date(mv.moved_at).toLocaleDateString() : '' }}</span
                                >
                            </li>
                        </ul>
                    </div>
                </CardContent>
            </Card>

            <!-- Refurbishment -->
            <Card v-show="activeTab === 'Refurbishment'">
                <CardHeader><CardTitle>Refurbishment Jobs</CardTitle></CardHeader>
                <CardContent class="grid gap-4">
                    <div v-if="can.workshop" class="grid gap-3 rounded-lg border p-3">
                        <p class="text-sm font-medium">New Job Card</p>
                        <div class="flex flex-wrap gap-2">
                            <select v-model="jobForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="internal">Internal</option>
                                <option value="external">External</option>
                            </select>
                            <select v-model="jobForm.vendor_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option :value="null">No vendor</option>
                                <option
                                    v-for="ven in vendors.filter((x) => x.type === 'workshop' || x.type === 'parts')"
                                    :key="ven.id"
                                    :value="ven.id"
                                >
                                    {{ ven.name }}
                                </option>
                            </select>
                            <Input v-model="jobForm.description" placeholder="Description" class="h-9 flex-1" />
                        </div>
                        <div v-for="(item, i) in jobForm.items" :key="i" class="flex flex-wrap items-center gap-2">
                            <Input v-model="item.description" placeholder="Work item" class="h-9 flex-1" />
                            <select v-model="item.work_type" class="h-9 rounded-md border border-input bg-transparent px-2 text-sm shadow-sm">
                                <option value="labour">Labour</option>
                                <option value="part">Part</option>
                            </select>
                            <Input v-model.number="item.estimate" type="number" min="0" placeholder="Est." class="h-9 w-28" />
                            <button v-if="jobForm.items.length > 1" class="text-brand-red" @click="removeJobItem(i)">✕</button>
                        </div>
                        <div class="flex gap-2">
                            <Button size="sm" variant="outline" @click="addJobItem">+ Item</Button>
                            <Button size="sm" :disabled="jobForm.processing" @click="createJob">Create Job</Button>
                        </div>
                    </div>
                    <ul v-if="vehicle.workshop_jobs?.length" class="divide-y text-sm">
                        <li v-for="j in vehicle.workshop_jobs" :key="j.id" class="flex items-center justify-between py-2">
                            <Link :href="`/admin/workshop/${j.id}`" class="font-mono text-xs underline">{{ j.job_number }}</Link>
                            <span class="flex items-center gap-2">
                                <span class="text-muted-foreground">{{ money(j.actual_total || j.approved_total || j.estimate_total) }}</span>
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{ (j.status ?? '').replace(/_/g, ' ') }}</span>
                            </span>
                        </li>
                    </ul>
                    <p v-else class="text-sm text-muted-foreground">No refurbishment jobs.</p>
                </CardContent>
            </Card>

            <!-- Publish -->
            <Card v-show="activeTab === 'Publish'">
                <CardHeader><CardTitle>Website Publication</CardTitle></CardHeader>
                <CardContent class="grid gap-3">
                    <p class="text-sm text-muted-foreground">
                        Publishing requires the vehicle to be Ready for Sale, with an asking price and at least one photo.
                    </p>
                    <div class="flex items-center gap-4 text-sm">
                        <span
                            >Website:
                            <strong :class="vehicle.published_web ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">{{
                                vehicle.published_web ? 'Live' : 'Not published'
                            }}</strong></span
                        >
                        <span
                            >Mobile app:
                            <strong :class="vehicle.published_mobile ? 'text-emerald-600 dark:text-emerald-400' : 'text-muted-foreground'">{{
                                vehicle.published_mobile ? 'Live' : 'Not published'
                            }}</strong></span
                        >
                    </div>
                    <div v-if="can.publish" class="flex gap-2">
                        <Button v-if="!vehicle.published_web" :disabled="publishForm.processing" @click="publish">Publish</Button>
                        <Button v-else variant="outline" @click="unpublish">Unpublish</Button>
                    </div>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
