<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    customer: Record<string, any>;
    canViewKyc: boolean;
    can: { update: boolean };
}>();

const c = computed(() => props.customer);
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/admin/customers' },
    { title: c.value.customer_code, href: '#' },
];
</script>

<template>
    <Head :title="customer.name" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">{{ customer.name }}</h1>
                    <p class="mt-1 text-sm text-muted-foreground">
                        {{ customer.customer_code }} · {{ customer.mobile }}<span v-if="customer.city"> · {{ customer.city }}</span>
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button v-if="can.update" as-child>
                        <Link :href="`/admin/customers/${customer.id}/edit`">Edit</Link>
                    </Button>
                    <Button variant="outline" as-child><Link href="/admin/customers">Back</Link></Button>
                </div>
            </div>

            <div class="grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader><CardTitle class="text-base">Profile</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-y-2 text-sm">
                        <span class="text-muted-foreground">Email</span><span>{{ customer.email ?? '—' }}</span>
                        <span class="text-muted-foreground">Alt Mobile</span><span>{{ customer.alt_mobile ?? '—' }}</span>
                        <span class="text-muted-foreground">City</span><span>{{ customer.city ?? '—' }}</span>
                        <span class="text-muted-foreground">Occupation</span><span>{{ customer.occupation ?? '—' }}</span>
                        <span class="text-muted-foreground">KYC</span><span class="capitalize">{{ customer.kyc_status }}</span>
                        <span class="text-muted-foreground">Branch</span><span>{{ customer.branch?.name ?? '—' }}</span>
                    </CardContent>
                </Card>

                <Card v-if="canViewKyc">
                    <CardHeader><CardTitle class="text-base">Identity &amp; Documents</CardTitle></CardHeader>
                    <CardContent class="grid gap-3">
                        <div class="grid grid-cols-2 gap-y-2 text-sm">
                            <span class="text-muted-foreground">Aadhaar</span><span>{{ customer.aadhaar_number ?? '—' }}</span>
                            <span class="text-muted-foreground">PAN</span><span>{{ customer.pan_number ?? '—' }}</span>
                        </div>
                        <div class="border-t pt-3">
                            <ul v-if="customer.documents?.length" class="divide-y text-sm">
                                <li v-for="d in customer.documents" :key="d.id" class="flex items-center justify-between py-2">
                                    <span class="capitalize">{{ d.type }}</span>
                                    <span class="rounded-full bg-muted px-2 py-0.5 text-xs capitalize">{{ d.status }}</span>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-muted-foreground">No documents.</p>
                        </div>
                    </CardContent>
                </Card>

                <Card class="lg:col-span-2">
                    <CardHeader><CardTitle class="text-base">Lead History</CardTitle></CardHeader>
                    <CardContent>
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th class="py-2 pr-3 font-medium">Lead #</th>
                                    <th class="py-2 pr-3 font-medium">Interest</th>
                                    <th class="py-2 pr-3 font-medium">Status</th>
                                    <th class="py-2 font-medium">Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="l in customer.sales_leads" :key="l.id" class="border-b last:border-0">
                                    <td class="py-2 pr-3">
                                        <Link :href="`/admin/sales-leads/${l.id}`" class="font-mono text-xs underline">{{ l.lead_number }}</Link>
                                    </td>
                                    <td class="py-2 pr-3">
                                        {{ l.interested_vehicle ? `${l.interested_vehicle.make} ${l.interested_vehicle.model}` : '—' }}
                                    </td>
                                    <td class="py-2 pr-3 capitalize">{{ (l.status ?? '').replace(/_/g, ' ') }}</td>
                                    <td class="py-2">{{ new Date(l.created_at).toLocaleDateString() }}</td>
                                </tr>
                                <tr v-if="!customer.sales_leads?.length">
                                    <td colspan="4" class="py-4 text-center text-muted-foreground">No leads.</td>
                                </tr>
                            </tbody>
                        </table>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
