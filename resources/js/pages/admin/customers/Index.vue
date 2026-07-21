<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, router } from '@inertiajs/vue3';
import { Search } from 'lucide-vue-next';
import { ref, watch } from 'vue';

interface CustomerRow {
    id: number;
    customer_code: string;
    name: string;
    mobile: string;
    city: string | null;
    kyc_status: string;
    branch?: { id: number; name: string } | null;
    sales_leads_count: number;
}

const props = defineProps<{
    customers: Paginated<CustomerRow>;
    filters: { search: string };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Customers', href: '/admin/customers' },
];

const search = ref(props.filters.search ?? '');
let timer: ReturnType<typeof setTimeout> | null = null;
watch(search, (v) => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => router.get('/admin/customers', v ? { search: v } : {}, { preserveState: true, replace: true }), 350);
});

const kycStyle: Record<string, string> = {
    verified: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    partial: 'bg-brand-orange/15 text-brand-orange',
    pending: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <Head title="Customers" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div>
                <h1 class="text-xl font-semibold">Customers</h1>
                <p class="text-sm text-muted-foreground">Unified customer directory across all leads.</p>
            </div>

            <div class="relative w-full max-w-sm">
                <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                <Input v-model="search" placeholder="Name, mobile, code…" class="pl-9" />
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[720px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Code</th>
                            <th class="px-4 py-3 font-medium">Name</th>
                            <th class="px-4 py-3 font-medium">Mobile</th>
                            <th class="px-4 py-3 font-medium">City</th>
                            <th class="px-4 py-3 font-medium">Leads</th>
                            <th class="px-4 py-3 font-medium">KYC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="customers.data.length === 0">
                            <td colspan="6" class="px-4 py-10 text-center text-muted-foreground">No customers yet.</td>
                        </tr>
                        <tr
                            v-for="c in customers.data"
                            :key="c.id"
                            class="cursor-pointer border-b last:border-0 hover:bg-muted/30"
                            @click="router.get(`/admin/customers/${c.id}`)"
                        >
                            <td class="px-4 py-3 font-mono text-xs">{{ c.customer_code }}</td>
                            <td class="px-4 py-3 font-medium">{{ c.name }}</td>
                            <td class="px-4 py-3">{{ c.mobile }}</td>
                            <td class="px-4 py-3">{{ c.city ?? '—' }}</td>
                            <td class="px-4 py-3">{{ c.sales_leads_count }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                    :class="kycStyle[c.kyc_status] ?? 'bg-muted'"
                                    >{{ c.kyc_status }}</span
                                >
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="customers" />
        </div>
    </AppLayout>
</template>
