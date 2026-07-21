<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    rtoCase: Record<string, any>;
    allowedTransitions: { value: string; label: string }[];
    agents: { id: number; name: string }[];
    executives: { id: number; name: string }[];
    can: { update: boolean; assign: boolean };
}>();

const c = computed(() => props.rtoCase);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'RTO Cases', href: '/admin/rto-cases' },
    { title: c.value.rto_number, href: '#' },
];

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '' || Number(v) === 0) return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}
const base = computed(() => `/admin/rto-cases/${c.value.id}`);

// Status transition.
const transitionForm = useForm({
    status: '',
    application_number: c.value.application_number ?? '',
    expected_completion: c.value.expected_completion ?? '',
    to_rto: c.value.to_rto ?? '',
    remarks: '',
});
function applyTransition() {
    if (!transitionForm.status) return;
    transitionForm.post(`${base.value}/transition`, { preserveScroll: true, onSuccess: () => transitionForm.reset('status', 'remarks') });
}

// Assignment.
const assignForm = useForm({ assigned_to: c.value.assignee?.id ?? null, agent_vendor_id: c.value.agent?.id ?? null });
function applyAssign() {
    assignForm.post(`${base.value}/assign`, { preserveScroll: true });
}

// Document movement.
const movementForm = useForm({ document: '', to_holder: '', from_holder: '', remarks: '' });
function addMovement() {
    if (!movementForm.document || !movementForm.to_holder) return;
    movementForm.post(`${base.value}/movements`, { preserveScroll: true, onSuccess: () => movementForm.reset() });
}

// Expense.
const expenseForm = useForm({ head: 'transfer_fee', amount: null as number | null, reference: '' });
function addExpense() {
    if (!expenseForm.amount) return;
    expenseForm.post(`${base.value}/expenses`, { preserveScroll: true, onSuccess: () => expenseForm.reset() });
}
const expenseHeads = [
    { value: 'transfer_fee', label: 'Transfer Fee' },
    { value: 'noc_fee', label: 'NOC Fee' },
    { value: 'smart_card', label: 'Smart Card' },
    { value: 'agent_fee', label: 'Agent Fee' },
    { value: 'other', label: 'Other' },
];

// Hold.
const holdForm = useForm({ amount: null as number | null, reason: '' });
function addHold() {
    if (!holdForm.amount) return;
    holdForm.post(`${base.value}/holds`, { preserveScroll: true, onSuccess: () => holdForm.reset() });
}
function releaseHold(id: number) {
    useForm({}).post(`/admin/rto-holds/${id}/release`, { preserveScroll: true });
}

// RC upload.
const rcForm = useForm({ rc_copy: null as File | null });
function onRcPicked(e: Event) {
    const target = e.target as HTMLInputElement;
    rcForm.rc_copy = target.files?.[0] ?? null;
}
function uploadRc() {
    if (!rcForm.rc_copy) return;
    rcForm.post(`${base.value}/rc`, { preserveScroll: true, forceFormData: true, onSuccess: () => rcForm.reset() });
}
</script>

<template>
    <Head :title="c.rto_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ c.rto_number }}</h1>
                        <span
                            class="rounded-full bg-brand-yellow/20 px-2.5 py-0.5 text-xs font-medium capitalize text-brand-maroon dark:text-brand-yellow"
                            >{{ c.status_label }}</span
                        >
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ c.vehicle?.make }} {{ c.vehicle?.model }} · {{ c.vehicle?.registration_number ?? c.vehicle?.stock_number }} · Buyer:
                        {{ c.buyer?.name ?? '—' }}
                        <template v-if="c.delivery">
                            · <Link :href="`/admin/deliveries/${c.delivery.id}`" class="underline">{{ c.delivery.delivery_number }}</Link></template
                        >
                    </p>
                </div>
                <Button variant="outline" as-child><Link href="/admin/rto-cases">Back</Link></Button>
            </div>

            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card
                    ><CardContent class="p-4"
                        ><p class="text-xs text-muted-foreground">From RTO</p>
                        <p class="text-lg font-bold">{{ c.from_rto ?? '—' }}</p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="p-4"
                        ><p class="text-xs text-muted-foreground">To RTO</p>
                        <p class="text-lg font-bold">{{ c.to_rto ?? '—' }}</p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="p-4"
                        ><p class="text-xs text-muted-foreground">Expenses</p>
                        <p class="text-lg font-bold">{{ money(c.total_expenses) }}</p></CardContent
                    ></Card
                >
                <Card
                    ><CardContent class="p-4"
                        ><p class="text-xs text-muted-foreground">On Hold</p>
                        <p class="text-lg font-bold text-brand-red">{{ money(c.hold_amount) }}</p></CardContent
                    ></Card
                >
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <!-- Document movement -->
                    <Card>
                        <CardHeader><CardTitle class="text-base">Document Custody</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="c.movements.length === 0" class="text-sm text-muted-foreground">No document movements recorded.</div>
                            <ul v-else class="divide-y text-sm">
                                <li v-for="m in c.movements" :key="m.id" class="flex items-center justify-between py-2">
                                    <span
                                        ><span class="font-medium">{{ m.document }}</span>
                                        <span class="text-muted-foreground">{{ m.from_holder ?? '—' }} → {{ m.to_holder }}</span></span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >{{ m.mover?.name }} · {{ m.moved_at ? new Date(m.moved_at).toLocaleDateString() : '' }}</span
                                    >
                                </li>
                            </ul>
                            <div v-if="can.update" class="grid gap-2 sm:grid-cols-4">
                                <Input v-model="movementForm.document" placeholder="Document" class="h-9" />
                                <Input v-model="movementForm.from_holder" placeholder="From (opt.)" class="h-9" />
                                <Input v-model="movementForm.to_holder" placeholder="To holder" class="h-9" />
                                <Button
                                    size="sm"
                                    :disabled="!movementForm.document || !movementForm.to_holder || movementForm.processing"
                                    @click="addMovement"
                                    >Record</Button
                                >
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Expenses -->
                    <Card>
                        <CardHeader><CardTitle class="text-base">Expenses</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="c.expenses.length === 0" class="text-sm text-muted-foreground">No expenses recorded.</div>
                            <ul v-else class="divide-y text-sm">
                                <li v-for="e in c.expenses" :key="e.id" class="flex items-center justify-between py-2">
                                    <span class="capitalize"
                                        >{{ e.head.replace(/_/g, ' ')
                                        }}<span v-if="e.reference" class="text-muted-foreground"> · {{ e.reference }}</span></span
                                    >
                                    <span class="font-medium">{{ money(e.amount) }}</span>
                                </li>
                            </ul>
                            <div v-if="can.update" class="grid gap-2 sm:grid-cols-4">
                                <select v-model="expenseForm.head" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option v-for="h in expenseHeads" :key="h.value" :value="h.value">{{ h.label }}</option>
                                </select>
                                <Input v-model.number="expenseForm.amount" type="number" placeholder="Amount" class="h-9" />
                                <Input v-model="expenseForm.reference" placeholder="Reference (opt.)" class="h-9" />
                                <Button size="sm" :disabled="!expenseForm.amount || expenseForm.processing" @click="addExpense">Add</Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Holds -->
                    <Card>
                        <CardHeader><CardTitle class="text-base">Payment Holds</CardTitle></CardHeader>
                        <CardContent class="space-y-3">
                            <div v-if="c.holds.length === 0" class="text-sm text-muted-foreground">No holds on this deal.</div>
                            <ul v-else class="divide-y text-sm">
                                <li v-for="h in c.holds" :key="h.id" class="flex items-center justify-between py-2">
                                    <span>
                                        <span class="font-medium">{{ money(h.amount) }}</span> — {{ h.reason }}
                                        <span
                                            class="ml-1 rounded-full px-1.5 py-0.5 text-xs"
                                            :class="
                                                h.status === 'held'
                                                    ? 'bg-brand-orange/15 text-brand-orange'
                                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400'
                                            "
                                            >{{ h.status }}</span
                                        >
                                    </span>
                                    <Button v-if="h.status === 'held' && can.update" size="sm" variant="ghost" @click="releaseHold(h.id)"
                                        >Release</Button
                                    >
                                </li>
                            </ul>
                            <div v-if="can.update" class="grid gap-2 sm:grid-cols-3">
                                <Input v-model.number="holdForm.amount" type="number" placeholder="Amount" class="h-9" />
                                <Input v-model="holdForm.reason" placeholder="Reason" class="h-9" />
                                <Button size="sm" :disabled="!holdForm.amount || !holdForm.reason || holdForm.processing" @click="addHold"
                                    >Place Hold</Button
                                >
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Status history -->
                    <Card>
                        <CardHeader><CardTitle class="text-base">Status History</CardTitle></CardHeader>
                        <CardContent>
                            <ol class="space-y-2 text-sm">
                                <li v-for="h in c.histories" :key="h.id" class="flex items-center justify-between border-b pb-2 last:border-0">
                                    <span class="capitalize"
                                        >{{ (h.to_status ?? '').replace(/_/g, ' ')
                                        }}<span v-if="h.remarks" class="text-muted-foreground"> — {{ h.remarks }}</span></span
                                    >
                                    <span class="text-xs text-muted-foreground"
                                        >{{ h.changed_by?.name ?? 'System' }} ·
                                        {{ h.created_at ? new Date(h.created_at).toLocaleDateString() : '' }}</span
                                    >
                                </li>
                            </ol>
                        </CardContent>
                    </Card>
                </div>

                <!-- Side rail -->
                <div class="space-y-4">
                    <Card v-if="can.update && allowedTransitions.length">
                        <CardHeader><CardTitle class="text-base">Advance Status</CardTitle></CardHeader>
                        <CardContent class="grid gap-2">
                            <select v-model="transitionForm.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="">Select next status…</option>
                                <option v-for="t in allowedTransitions" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <Input v-model="transitionForm.application_number" placeholder="Application # (opt.)" class="h-9" />
                            <Input v-model="transitionForm.to_rto" placeholder="Destination RTO (opt.)" class="h-9" />
                            <div class="grid gap-1">
                                <Label class="text-xs">Expected completion</Label
                                ><Input v-model="transitionForm.expected_completion" type="date" class="h-9" />
                            </div>
                            <Input v-model="transitionForm.remarks" placeholder="Remarks (opt.)" class="h-9" />
                            <Button size="sm" :disabled="!transitionForm.status || transitionForm.processing" @click="applyTransition">Apply</Button>
                        </CardContent>
                    </Card>

                    <Card v-if="can.assign">
                        <CardHeader><CardTitle class="text-base">Assignment</CardTitle></CardHeader>
                        <CardContent class="grid gap-2">
                            <div class="grid gap-1">
                                <Label class="text-xs">RTO Executive</Label>
                                <select
                                    v-model="assignForm.assigned_to"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                >
                                    <option :value="null">Unassigned</option>
                                    <option v-for="u in executives" :key="u.id" :value="u.id">{{ u.name }}</option>
                                </select>
                            </div>
                            <div class="grid gap-1">
                                <Label class="text-xs">Agent / Vendor</Label>
                                <select
                                    v-model="assignForm.agent_vendor_id"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                >
                                    <option :value="null">None</option>
                                    <option v-for="a in agents" :key="a.id" :value="a.id">{{ a.name }}</option>
                                </select>
                            </div>
                            <Button size="sm" :disabled="assignForm.processing" @click="applyAssign">Save Assignment</Button>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle class="text-base">Registration Certificate</CardTitle></CardHeader>
                        <CardContent class="grid gap-2 text-sm">
                            <a v-if="c.rc_copy_path" :href="`/admin/files/${c.rc_copy_path}`" class="underline" target="_blank">View uploaded RC</a>
                            <p v-else class="text-muted-foreground">No RC uploaded yet.</p>
                            <template v-if="can.update">
                                <input type="file" accept=".pdf,.jpg,.jpeg,.png" class="text-xs" @change="onRcPicked" />
                                <Button size="sm" :disabled="!rcForm.rc_copy || rcForm.processing" @click="uploadRc">Upload RC</Button>
                            </template>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle class="text-base">Details</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-muted-foreground">Application #</span><span>{{ c.application_number ?? '—' }}</span>
                            <span class="text-muted-foreground">Sale date</span><span>{{ c.sale_date ?? '—' }}</span>
                            <span class="text-muted-foreground">Delivery date</span><span>{{ c.delivery_date ?? '—' }}</span>
                            <span class="text-muted-foreground">Seller</span><span>{{ c.seller?.name ?? '—' }}</span>
                            <span class="text-muted-foreground">Branch</span><span>{{ c.branch?.name ?? '—' }}</span>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
