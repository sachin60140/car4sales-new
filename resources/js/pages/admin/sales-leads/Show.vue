<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps<{
    lead: Record<string, any>;
    customerHistory: { id: number; lead_number: string; status: string; created_at: string }[];
    callOutcomes: { value: string; label: string; connected: boolean }[];
    lostReasons: { id: number; label: string }[];
    allowedTransitions: { value: string; label: string }[];
    telecallers: { id: number; name: string }[];
    salesExecutives: { id: number; name: string }[];
    availableVehicles: { id: number; stock_number: string; make: string; model: string; asking_price: string | null; status: string }[];
    can: Record<string, boolean>;
}>();

const lead = computed(() => props.lead);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Sales Leads', href: '/admin/sales-leads' },
    { title: lead.value.lead_number, href: '#' },
];

// Outcomes that require a follow-up date / lost reason (mirrors backend rules).
const followupOutcomes = [
    'call_later',
    'interested',
    'visit_scheduled',
    'test_drive_scheduled',
    'finance_required',
    'exchange_required',
    'booking_expected',
    'busy',
    'no_answer',
    'switched_off',
];
const lostOutcomes = ['wrong_number', 'not_interested'];

const callForm = useForm({
    outcome: 'connected',
    channel: 'call',
    remarks: '',
    next_follow_up_at: '',
    duration_seconds: null as number | null,
    lost_reason_id: null as number | null,
});
const needsFollowup = computed(() => followupOutcomes.includes(callForm.outcome));
const needsReason = computed(() => lostOutcomes.includes(callForm.outcome));
function logCall() {
    callForm.post(`/admin/sales-leads/${lead.value.id}/call`, {
        preserveScroll: true,
        onSuccess: () => callForm.reset(),
    });
}

const transitionForm = useForm({ status: '', remarks: '', lost_reason_id: null as number | null });
const lostStatuses = ['lost', 'wrong_number', 'duplicate'];
const transitionNeedsReason = computed(() => lostStatuses.includes(transitionForm.status));
function applyTransition() {
    if (!transitionForm.status) return;
    transitionForm.post(`/admin/sales-leads/${lead.value.id}/transition`, {
        preserveScroll: true,
        onSuccess: () => transitionForm.reset(),
    });
}

const assignForm = useForm({ role: 'telecaller', user_id: null as number | null, reason: '' });
function assign() {
    assignForm.post(`/admin/sales-leads/${lead.value.id}/assign`, {
        preserveScroll: true,
        onSuccess: () => assignForm.reset(),
    });
}

function dial(mobile: string) {
    window.location.href = `tel:${mobile}`;
}

// Schedule visit
const visitForm = useForm({ scheduled_at: '', remarks: '' });
function scheduleVisit() {
    visitForm.post(`/admin/sales-leads/${lead.value.id}/visits`, { preserveScroll: true, onSuccess: () => visitForm.reset() });
}
// Schedule test drive
const tdForm = useForm({ vehicle_id: null as number | null, scheduled_at: '', driving_licence_number: '' });
function scheduleTestDrive() {
    tdForm.post(`/admin/sales-leads/${lead.value.id}/test-drives`, { preserveScroll: true, onSuccess: () => tdForm.reset() });
}
// Create booking
const bookingForm = useForm({
    vehicle_id: null as number | null,
    selling_price: null as number | null,
    booking_amount: 0,
    discount_amount: 0,
    payment_mode: 'cash',
});
function createBooking() {
    bookingForm.transform((d) => ({ ...d, sales_lead_id: lead.value.id })).post('/admin/bookings', { preserveScroll: false });
}
const showBookingForm = ref(false);
const showTdForm = ref(false);

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const statusStyle: Record<string, string> = {
    new: 'bg-brand-orange/15 text-brand-orange',
    interested: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    lost: 'bg-brand-red/15 text-brand-red',
    wrong_number: 'bg-brand-red/15 text-brand-red',
    duplicate: 'bg-brand-red/15 text-brand-red',
};
</script>

<template>
    <Head :title="lead.lead_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <!-- Header -->
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ lead.name }}</h1>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize"
                            :class="statusStyle[lead.status] ?? 'bg-brand-yellow/20 text-brand-maroon dark:text-brand-yellow'"
                        >
                            {{ (lead.status ?? '').replace(/_/g, ' ') }}
                        </span>
                        <span class="rounded-full bg-muted px-2 py-0.5 text-xs font-medium capitalize">{{ lead.priority }}</span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ lead.lead_number }} · {{ lead.mobile }} <span v-if="lead.city">· {{ lead.city }}</span> · Source: {{ lead.source }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" size="sm" @click="dial(lead.mobile)"><span class="mr-1">📞</span> Call</Button>
                    <Button variant="outline" as-child><Link href="/admin/sales-leads">Back</Link></Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <!-- Left: log call + details -->
                <div class="space-y-4 lg:col-span-2">
                    <Card v-if="can.update">
                        <CardHeader><CardTitle class="text-base">Log Call / Follow-up</CardTitle></CardHeader>
                        <CardContent class="grid gap-3">
                            <div class="grid gap-3 sm:grid-cols-2">
                                <div class="grid gap-1">
                                    <Label class="text-xs">Outcome</Label>
                                    <select
                                        v-model="callForm.outcome"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                    >
                                        <option v-for="o in callOutcomes" :key="o.value" :value="o.value">{{ o.label }}</option>
                                    </select>
                                </div>
                                <div class="grid gap-1">
                                    <Label class="text-xs">Channel</Label>
                                    <select
                                        v-model="callForm.channel"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                    >
                                        <option value="call">Call</option>
                                        <option value="whatsapp">WhatsApp</option>
                                        <option value="sms">SMS</option>
                                        <option value="email">Email</option>
                                    </select>
                                </div>
                            </div>
                            <div v-if="needsFollowup" class="grid gap-1">
                                <Label class="text-xs">Next Follow-up *</Label>
                                <Input v-model="callForm.next_follow_up_at" type="datetime-local" class="h-9" />
                                <p v-if="callForm.errors.next_follow_up_at" class="text-xs text-brand-red">{{ callForm.errors.next_follow_up_at }}</p>
                            </div>
                            <div v-if="needsReason" class="grid gap-1">
                                <Label class="text-xs">Lost Reason *</Label>
                                <select
                                    v-model="callForm.lost_reason_id"
                                    class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                >
                                    <option :value="null">Select reason…</option>
                                    <option v-for="r in lostReasons" :key="r.id" :value="r.id">{{ r.label }}</option>
                                </select>
                                <p v-if="callForm.errors.lost_reason_id" class="text-xs text-brand-red">{{ callForm.errors.lost_reason_id }}</p>
                            </div>
                            <textarea
                                v-model="callForm.remarks"
                                rows="2"
                                placeholder="Remarks"
                                class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm"
                            />
                            <div><Button size="sm" :disabled="callForm.processing" @click="logCall">Log Call</Button></div>
                        </CardContent>
                    </Card>

                    <!-- Sales actions: visit / test drive / booking -->
                    <Card v-if="can.scheduleVisit || can.scheduleTestDrive || can.createBooking">
                        <CardHeader><CardTitle class="text-base">Sales Actions</CardTitle></CardHeader>
                        <CardContent class="grid gap-4">
                            <!-- Schedule visit -->
                            <div v-if="can.scheduleVisit" class="flex flex-wrap items-end gap-2 border-b pb-3">
                                <div class="grid gap-1">
                                    <Label class="text-xs">Schedule Visit</Label>
                                    <Input v-model="visitForm.scheduled_at" type="datetime-local" class="h-9" />
                                </div>
                                <Button size="sm" variant="outline" :disabled="!visitForm.scheduled_at || visitForm.processing" @click="scheduleVisit"
                                    >Book Visit</Button
                                >
                            </div>

                            <!-- Schedule test drive -->
                            <div v-if="can.scheduleTestDrive" class="border-b pb-3">
                                <Button v-if="!showTdForm" size="sm" variant="outline" @click="showTdForm = true">+ Schedule Test Drive</Button>
                                <div v-else class="grid gap-2">
                                    <select
                                        v-model="tdForm.vehicle_id"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                    >
                                        <option :value="null">Select vehicle…</option>
                                        <option v-for="v in availableVehicles" :key="v.id" :value="v.id">
                                            {{ v.stock_number }} — {{ v.make }} {{ v.model }}
                                        </option>
                                    </select>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <Input v-model="tdForm.scheduled_at" type="datetime-local" class="h-9" />
                                        <Input v-model="tdForm.driving_licence_number" placeholder="DL number" class="h-9 w-40" />
                                        <Button
                                            size="sm"
                                            :disabled="!tdForm.vehicle_id || !tdForm.scheduled_at || tdForm.processing"
                                            @click="scheduleTestDrive"
                                            >Schedule</Button
                                        >
                                    </div>
                                </div>
                            </div>

                            <!-- Create booking -->
                            <div v-if="can.createBooking">
                                <Button v-if="!showBookingForm" size="sm" @click="showBookingForm = true">+ Create Booking</Button>
                                <div v-else class="grid gap-2">
                                    <select
                                        v-model="bookingForm.vehicle_id"
                                        class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                    >
                                        <option :value="null">Select vehicle…</option>
                                        <option v-for="v in availableVehicles" :key="v.id" :value="v.id">
                                            {{ v.stock_number }} — {{ v.make }} {{ v.model }} ({{ money(v.asking_price) }})
                                        </option>
                                    </select>
                                    <div class="grid grid-cols-2 gap-2">
                                        <Input v-model.number="bookingForm.selling_price" type="number" placeholder="Selling price" class="h-9" />
                                        <Input v-model.number="bookingForm.discount_amount" type="number" placeholder="Discount" class="h-9" />
                                        <Input v-model.number="bookingForm.booking_amount" type="number" placeholder="Booking amount" class="h-9" />
                                        <select
                                            v-model="bookingForm.payment_mode"
                                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                                        >
                                            <option value="cash">Cash</option>
                                            <option value="finance">Finance</option>
                                        </select>
                                    </div>
                                    <div class="flex gap-2">
                                        <Button
                                            size="sm"
                                            :disabled="!bookingForm.vehicle_id || !bookingForm.selling_price || bookingForm.processing"
                                            @click="createBooking"
                                            >Create Booking</Button
                                        >
                                        <Button size="sm" variant="ghost" @click="showBookingForm = false">Cancel</Button>
                                    </div>
                                </div>
                            </div>

                            <!-- Existing sales records -->
                            <div
                                v-if="lead.bookings?.length || lead.test_drives?.length || lead.visits?.length"
                                class="grid gap-1 border-t pt-3 text-sm"
                            >
                                <Link
                                    v-for="b in lead.bookings"
                                    :key="'b' + b.id"
                                    :href="`/admin/bookings/${b.id}`"
                                    class="flex items-center justify-between hover:underline"
                                >
                                    <span class="font-mono text-xs">{{ b.booking_number }}</span>
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{
                                        (b.status ?? '').replace(/_/g, ' ')
                                    }}</span>
                                </Link>
                                <div v-for="t in lead.test_drives" :key="'t' + t.id" class="flex items-center justify-between text-muted-foreground">
                                    <span class="font-mono text-xs">{{ t.td_number }}</span
                                    ><span class="text-xs capitalize">test drive · {{ t.status }}</span>
                                </div>
                                <div v-for="vi in lead.visits" :key="'v' + vi.id" class="flex items-center justify-between text-muted-foreground">
                                    <span class="font-mono text-xs">{{ vi.visit_number }}</span
                                    ><span class="text-xs capitalize">visit · {{ (vi.status ?? '').replace(/_/g, ' ') }}</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Activity timeline -->
                    <Card>
                        <CardHeader><CardTitle class="text-base">Activity Timeline</CardTitle></CardHeader>
                        <CardContent>
                            <ol class="space-y-3">
                                <li v-for="a in lead.activities" :key="a.id" class="flex gap-3 border-b pb-3 text-sm last:border-0 last:pb-0">
                                    <span
                                        class="mt-1 size-2 shrink-0 rounded-full"
                                        :class="
                                            a.type === 'call'
                                                ? 'bg-brand-orange'
                                                : a.type === 'status'
                                                  ? 'bg-brand-maroon dark:bg-brand-yellow'
                                                  : 'bg-muted-foreground'
                                        "
                                    />
                                    <div class="flex-1">
                                        <p>{{ a.summary }}</p>
                                        <p class="text-xs text-muted-foreground">
                                            {{ a.user?.name ?? 'System' }} · {{ new Date(a.created_at).toLocaleString() }}
                                        </p>
                                    </div>
                                    <span class="text-xs capitalize text-muted-foreground">{{ a.type }}</span>
                                </li>
                                <li v-if="!lead.activities?.length" class="py-4 text-center text-sm text-muted-foreground">No activity yet.</li>
                            </ol>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: status, assignment, customer -->
                <div class="space-y-4">
                    <Card v-if="can.update && allowedTransitions.length">
                        <CardHeader><CardTitle class="text-base">Update Status</CardTitle></CardHeader>
                        <CardContent class="grid gap-2">
                            <select v-model="transitionForm.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                <option value="">Select status…</option>
                                <option v-for="t in allowedTransitions" :key="t.value" :value="t.value">{{ t.label }}</option>
                            </select>
                            <select
                                v-if="transitionNeedsReason"
                                v-model="transitionForm.lost_reason_id"
                                class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                            >
                                <option :value="null">Lost reason…</option>
                                <option v-for="r in lostReasons" :key="r.id" :value="r.id">{{ r.label }}</option>
                            </select>
                            <p v-if="transitionForm.errors.lost_reason_id" class="text-xs text-brand-red">
                                {{ transitionForm.errors.lost_reason_id }}
                            </p>
                            <Button size="sm" :disabled="!transitionForm.status || transitionForm.processing" @click="applyTransition">Apply</Button>
                        </CardContent>
                    </Card>

                    <Card v-if="can.assign">
                        <CardHeader><CardTitle class="text-base">Assignment</CardTitle></CardHeader>
                        <CardContent class="grid gap-2 text-sm">
                            <p class="text-muted-foreground">
                                Telecaller: <strong class="text-foreground">{{ lead.telecaller?.name ?? 'Unassigned' }}</strong>
                            </p>
                            <p class="text-muted-foreground">
                                Sales Exec: <strong class="text-foreground">{{ lead.sales_executive?.name ?? 'Unassigned' }}</strong>
                            </p>
                            <div class="mt-1 grid gap-2 border-t pt-2">
                                <select v-model="assignForm.role" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option value="telecaller">Telecaller</option>
                                    <option value="sales_executive">Sales Executive</option>
                                </select>
                                <select v-model="assignForm.user_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option :value="null">Unassign</option>
                                    <option v-for="u in assignForm.role === 'telecaller' ? telecallers : salesExecutives" :key="u.id" :value="u.id">
                                        {{ u.name }}
                                    </option>
                                </select>
                                <Button size="sm" variant="outline" :disabled="assignForm.processing" @click="assign">Assign</Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader><CardTitle class="text-base">Lead Details</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-muted-foreground">Budget</span
                            ><span>{{
                                lead.budget_min || lead.budget_max
                                    ? `₹${Number(lead.budget_min ?? 0).toLocaleString('en-IN')} – ₹${Number(lead.budget_max ?? 0).toLocaleString('en-IN')}`
                                    : '—'
                            }}</span>
                            <span class="text-muted-foreground">Finance</span><span>{{ lead.finance_required ? 'Required' : 'No' }}</span>
                            <span class="text-muted-foreground">Exchange</span><span>{{ lead.exchange_required ? 'Required' : 'No' }}</span>
                            <span class="text-muted-foreground">Interested in</span>
                            <span>
                                <Link v-if="lead.interested_vehicle" :href="`/admin/inventory/${lead.interested_vehicle.id}`" class="underline">{{
                                    lead.interested_vehicle.stock_number
                                }}</Link>
                                <span v-else>—</span>
                            </span>
                            <span class="text-muted-foreground">Branch</span><span>{{ lead.branch?.name ?? '—' }}</span>
                        </CardContent>
                    </Card>

                    <Card v-if="customerHistory.length">
                        <CardHeader><CardTitle class="text-base">Customer History</CardTitle></CardHeader>
                        <CardContent>
                            <ul class="space-y-1 text-sm">
                                <li v-for="h in customerHistory" :key="h.id" class="flex items-center justify-between">
                                    <Link :href="`/admin/sales-leads/${h.id}`" class="font-mono text-xs underline">{{ h.lead_number }}</Link>
                                    <span class="text-xs capitalize text-muted-foreground">{{ (h.status ?? '').replace(/_/g, ' ') }}</span>
                                </li>
                            </ul>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
