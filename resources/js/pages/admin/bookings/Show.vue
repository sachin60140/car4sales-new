<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    booking: Record<string, any>;
    netPayable: number;
    paidAmount: number;
    ledger: { outstanding: number; entries: { id: number; type: string; head: string; amount: string; is_reversal: boolean; remarks: string | null; posted_at: string }[] } | null;
    financeApplication: { id: number; application_number: string; status: string } | null;
    invoice: { invoice_number: string; total: string; download: string | null } | null;
    allowedTransitions: { value: string; label: string }[];
    can: Record<string, boolean>;
}>();

const b = computed(() => props.booking);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Bookings', href: '/admin/bookings' },
    { title: b.value.booking_number, href: '#' },
];

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}
function post(url: string, data: Record<string, string | number | null> = {}, done?: () => void) {
    router.post(url, data, { preserveScroll: true, onSuccess: done });
}

const paymentForm = useForm({ type: 'booking', amount: 0, method: 'cash', reference: '' });
function addPayment() {
    paymentForm.post(`/admin/bookings/${b.value.id}/payment`, { preserveScroll: true, onSuccess: () => paymentForm.reset() });
}

const cancelForm = useForm({ reason: '', refund_amount: 0, forfeit_amount: 0 });
function requestCancel() {
    cancelForm.post(`/admin/bookings/${b.value.id}/cancel`, { preserveScroll: true, onSuccess: () => cancelForm.reset() });
}

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    approval_pending: 'bg-brand-orange/15 text-brand-orange',
    confirmed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    delivered: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    cancelled: 'bg-brand-red/15 text-brand-red',
    refunded: 'bg-brand-red/15 text-brand-red',
};

function reversePayment(id: number) {
    const remarks = prompt('Reason for reversing this payment?');
    if (remarks) post(`/admin/booking-payments/${id}/reverse`, { remarks });
}

const financeForm = useForm({ loan_amount: 0, down_payment: 0, tenure_months: 60 });
function createFinance() {
    financeForm.transform((d) => ({ ...d, booking_id: b.value.id })).post('/admin/finance');
}

const pendingCancellation = computed(() => (b.value.cancellations ?? []).find((c: any) => c.status === 'requested'));
const approvedCancellation = computed(() => (b.value.cancellations ?? []).find((c: any) => c.status === 'approved'));
const pendingRefund = computed(() => (b.value.refunds ?? []).find((r: any) => r.status === 'approved'));
</script>

<template>
    <Head :title="booking.booking_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ booking.booking_number }}</h1>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-medium capitalize" :class="statusStyle[booking.status] ?? 'bg-brand-yellow/20 text-brand-maroon dark:text-brand-yellow'">
                            {{ (booking.status ?? '').replace(/_/g, ' ') }}
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ booking.customer?.name }} · {{ booking.customer?.mobile }} ·
                        <Link v-if="booking.vehicle" :href="`/admin/inventory/${booking.vehicle.id}`" class="underline">{{ booking.vehicle.stock_number }}</Link>
                        {{ booking.vehicle ? `${booking.vehicle.make} ${booking.vehicle.model}` : '' }}
                    </p>
                </div>
                <Button variant="outline" as-child><Link href="/admin/bookings">Back</Link></Button>
            </div>

            <!-- Money summary -->
            <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Selling Price</p><p class="text-lg font-bold">{{ money(booking.selling_price) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Discount</p><p class="text-lg font-bold">{{ money(booking.discount_amount) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Net Payable</p><p class="text-lg font-bold text-brand-maroon dark:text-brand-yellow">{{ money(netPayable) }}</p></CardContent></Card>
                <Card><CardContent class="p-4"><p class="text-xs text-muted-foreground">Paid</p><p class="text-lg font-bold text-emerald-600">{{ money(paidAmount) }}</p></CardContent></Card>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <div class="space-y-4 lg:col-span-2">
                    <!-- Discount approval status -->
                    <Card v-if="booking.status === 'approval_pending' && booking.approval_request">
                        <CardContent class="flex items-center gap-2 py-4 text-sm">
                            <span class="rounded-full bg-brand-orange/15 px-2 py-0.5 text-xs font-medium text-brand-orange">Awaiting discount approval</span>
                            <span class="text-muted-foreground">{{ booking.approval_request.approval_number }} — routed to the approval inbox.</span>
                        </CardContent>
                    </Card>

                    <!-- Confirm draft -->
                    <Card v-if="can.confirm && booking.status === 'draft'">
                        <CardContent class="flex items-center gap-3 py-4">
                            <Button @click="post(`/admin/bookings/${booking.id}/confirm`)">Confirm Booking</Button>
                            <p class="text-sm text-muted-foreground">Locks the vehicle. Excess discount will route for approval.</p>
                        </CardContent>
                    </Card>

                    <!-- Payments -->
                    <Card>
                        <CardHeader><CardTitle class="text-base">Payments</CardTitle></CardHeader>
                        <CardContent class="grid gap-3">
                            <div v-if="can.update && !['cancelled','refunded','forfeited'].includes(booking.status)" class="flex flex-wrap items-end gap-2">
                                <select v-model="paymentForm.type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option value="token">Token</option><option value="booking">Booking</option><option value="advance">Advance</option><option value="balance">Balance</option>
                                </select>
                                <Input v-model.number="paymentForm.amount" type="number" placeholder="Amount" class="h-9 w-32" />
                                <select v-model="paymentForm.method" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                                    <option value="cash">Cash</option><option value="upi">UPI</option><option value="card">Card</option><option value="bank_transfer">Bank</option><option value="cheque">Cheque</option>
                                </select>
                                <Input v-model="paymentForm.reference" placeholder="Reference" class="h-9 w-36" />
                                <Button size="sm" :disabled="paymentForm.amount <= 0 || paymentForm.processing" @click="addPayment">Add</Button>
                            </div>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left text-muted-foreground">
                                        <th class="py-2 pr-3 font-medium">Payment #</th><th class="py-2 pr-3 font-medium">Type</th><th class="py-2 pr-3 font-medium">Amount</th><th class="py-2 pr-3 font-medium">Method</th><th class="py-2 font-medium text-right">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!booking.payments?.length"><td colspan="5" class="py-4 text-center text-muted-foreground">No payments.</td></tr>
                                    <tr v-for="p in booking.payments" :key="p.id" class="border-b last:border-0">
                                        <td class="py-2 pr-3 font-mono text-xs">{{ p.payment_number }}</td>
                                        <td class="py-2 pr-3 capitalize">{{ p.type }}</td>
                                        <td class="py-2 pr-3" :class="Number(p.amount) < 0 ? 'text-brand-red' : ''">{{ money(p.amount) }}</td>
                                        <td class="py-2 pr-3 capitalize">{{ p.method }}</td>
                                        <td class="py-2 text-right">
                                            <button v-if="can.reversePayment && p.status === 'received' && p.type !== 'refund'" class="text-xs text-brand-red underline" @click="reversePayment(p.id)">Reverse</button>
                                            <span v-else class="text-xs capitalize text-muted-foreground">{{ p.status }}</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </CardContent>
                    </Card>

                    <!-- Customer ledger -->
                    <Card v-if="ledger">
                        <CardHeader class="flex flex-row items-center justify-between">
                            <CardTitle class="text-base">Customer Ledger</CardTitle>
                            <span class="text-sm">Outstanding: <strong :class="ledger.outstanding > 0 ? 'text-brand-red' : 'text-emerald-600'">{{ money(ledger.outstanding) }}</strong></span>
                        </CardHeader>
                        <CardContent>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="border-b text-left text-muted-foreground">
                                        <th class="py-2 pr-3 font-medium">Head</th><th class="py-2 pr-3 font-medium">Debit</th><th class="py-2 pr-3 font-medium">Credit</th><th class="py-2 font-medium">When</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="e in ledger.entries" :key="e.id" class="border-b last:border-0" :class="e.is_reversal ? 'text-muted-foreground italic' : ''">
                                        <td class="py-2 pr-3 capitalize">{{ (e.head ?? '').replace(/_/g, ' ') }}<span v-if="e.is_reversal"> (reversal)</span></td>
                                        <td class="py-2 pr-3">{{ e.type === 'debit' ? money(e.amount) : '' }}</td>
                                        <td class="py-2 pr-3">{{ e.type === 'credit' ? money(e.amount) : '' }}</td>
                                        <td class="py-2 text-xs text-muted-foreground">{{ new Date(e.posted_at).toLocaleDateString() }}</td>
                                    </tr>
                                </tbody>
                            </table>
                            <p class="mt-2 text-xs text-muted-foreground">Ledger entries are append-only; corrections post a reversal, never a delete.</p>
                        </CardContent>
                    </Card>

                    <!-- Status history -->
                    <Card>
                        <CardHeader><CardTitle class="text-base">Status History</CardTitle></CardHeader>
                        <CardContent>
                            <ol class="space-y-2 text-sm">
                                <li v-for="h in booking.status_histories" :key="h.id" class="flex items-center justify-between border-b pb-2 last:border-0">
                                    <span class="capitalize">{{ (h.to_status ?? '').replace(/_/g, ' ') }}<span v-if="h.remarks" class="text-muted-foreground"> — {{ h.remarks }}</span></span>
                                    <span class="text-xs text-muted-foreground">{{ h.changer?.name ?? 'System' }} · {{ new Date(h.created_at).toLocaleDateString() }}</span>
                                </li>
                            </ol>
                        </CardContent>
                    </Card>
                </div>

                <!-- Right: details + cancellation/refund -->
                <div class="space-y-4">
                    <Card>
                        <CardHeader><CardTitle class="text-base">Details</CardTitle></CardHeader>
                        <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-muted-foreground">Payment mode</span><span class="capitalize">{{ booking.payment_mode }}</span>
                            <span class="text-muted-foreground">Booking amount</span><span>{{ money(booking.booking_amount) }}</span>
                            <span class="text-muted-foreground">Exchange</span><span>{{ money(booking.exchange_adjustment) }}</span>
                            <span class="text-muted-foreground">Sales exec</span><span>{{ booking.sales_executive?.name ?? '—' }}</span>
                            <span class="text-muted-foreground">Branch</span><span>{{ booking.branch?.name ?? '—' }}</span>
                            <span class="text-muted-foreground">Lead</span>
                            <span><Link v-if="booking.lead" :href="`/admin/sales-leads/${booking.lead.id}`" class="underline">{{ booking.lead.lead_number }}</Link><span v-else>—</span></span>
                        </CardContent>
                    </Card>

                    <!-- Finance file -->
                    <Card v-if="booking.payment_mode === 'finance'">
                        <CardHeader><CardTitle class="text-base">Finance</CardTitle></CardHeader>
                        <CardContent class="grid gap-2 text-sm">
                            <div v-if="financeApplication" class="flex items-center justify-between">
                                <Link :href="`/admin/finance/${financeApplication.id}`" class="font-mono text-xs underline">{{ financeApplication.application_number }}</Link>
                                <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{ (financeApplication.status ?? '').replace(/_/g, ' ') }}</span>
                            </div>
                            <div v-else-if="can.finance" class="grid gap-2">
                                <div class="grid grid-cols-2 gap-2">
                                    <Input v-model.number="financeForm.loan_amount" type="number" placeholder="Loan amount" class="h-9" />
                                    <Input v-model.number="financeForm.down_payment" type="number" placeholder="Down payment" class="h-9" />
                                </div>
                                <Button size="sm" :disabled="financeForm.processing" @click="createFinance">Open Finance File</Button>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Invoice -->
                    <Card>
                        <CardHeader><CardTitle class="text-base">Invoice</CardTitle></CardHeader>
                        <CardContent class="grid gap-2 text-sm">
                            <div v-if="invoice" class="flex items-center justify-between">
                                <span class="font-mono text-xs">{{ invoice.invoice_number }} · {{ money(invoice.total) }}</span>
                                <a v-if="invoice.download" :href="invoice.download" class="text-xs text-brand-maroon underline dark:text-brand-yellow">Download PDF</a>
                            </div>
                            <Button v-else-if="can.invoice && ['confirmed','payment_pending','finance_pending','ready_for_delivery','delivered'].includes(booking.status)" size="sm" variant="outline" @click="post(`/admin/bookings/${booking.id}/invoice`)">Generate Invoice</Button>
                            <p v-else class="text-muted-foreground">Invoice available after confirmation.</p>
                        </CardContent>
                    </Card>

                    <!-- Cancellation -->
                    <Card v-if="can.cancel && ['confirmed','payment_pending','finance_pending','ready_for_delivery'].includes(booking.status)">
                        <CardHeader><CardTitle class="text-base">Request Cancellation</CardTitle></CardHeader>
                        <CardContent class="grid gap-2">
                            <textarea v-model="cancelForm.reason" rows="2" placeholder="Reason (required)" class="rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-sm" />
                            <div class="grid grid-cols-2 gap-2">
                                <div class="grid gap-1"><Label class="text-xs">Refund ₹</Label><Input v-model.number="cancelForm.refund_amount" type="number" class="h-9" /></div>
                                <div class="grid gap-1"><Label class="text-xs">Forfeit ₹</Label><Input v-model.number="cancelForm.forfeit_amount" type="number" class="h-9" /></div>
                            </div>
                            <Button size="sm" variant="destructive" :disabled="!cancelForm.reason || cancelForm.processing" @click="requestCancel">Request Cancellation</Button>
                        </CardContent>
                    </Card>

                    <!-- Approve cancellation -->
                    <Card v-if="pendingCancellation && can.approveCancel">
                        <CardHeader><CardTitle class="text-base">Approve Cancellation</CardTitle></CardHeader>
                        <CardContent class="grid gap-2 text-sm">
                            <p class="text-muted-foreground">{{ pendingCancellation.reason }}</p>
                            <p>Refund: {{ money(pendingCancellation.refund_amount) }} · Forfeit: {{ money(pendingCancellation.forfeit_amount) }}</p>
                            <Button size="sm" @click="post(`/admin/booking-cancellations/${pendingCancellation.id}/approve`)">Approve &amp; Release Vehicle</Button>
                        </CardContent>
                    </Card>

                    <!-- Initiate refund -->
                    <Card v-if="approvedCancellation && Number(approvedCancellation.refund_amount) > 0 && booking.status === 'refund_pending' && (booking.refunds?.length ?? 0) === 0 && can.refund">
                        <CardContent class="py-4">
                            <Button size="sm" @click="post(`/admin/booking-cancellations/${approvedCancellation.id}/refund`)">Raise Refund for Approval ({{ money(approvedCancellation.refund_amount) }})</Button>
                        </CardContent>
                    </Card>

                    <!-- Pay approved refund -->
                    <Card v-if="pendingRefund && can.refund">
                        <CardHeader><CardTitle class="text-base">Pay Refund</CardTitle></CardHeader>
                        <CardContent class="grid gap-2">
                            <p class="text-sm">{{ pendingRefund.refund_number }} — {{ money(pendingRefund.amount) }} (approved)</p>
                            <Button size="sm" @click="post(`/admin/refunds/${pendingRefund.id}/pay`, { method: 'bank_transfer' })">Mark Refund Paid</Button>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
