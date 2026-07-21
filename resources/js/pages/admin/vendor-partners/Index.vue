<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Paginated } from '@/types';
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Search } from 'lucide-vue-next';
import { reactive, watch } from 'vue';

interface Partner {
    id: number;
    name: string;
    email: string;
    company_name: string | null;
    phone: string | null;
    city: string | null;
    gst_number: string | null;
    status: string;
    status_label: string;
    created_at: string;
}

const props = defineProps<{
    partners: Paginated<Partner>;
    statuses: { value: string; label: string }[];
    filters: { search: string; status: string | null };
    can: { activate: boolean; create: boolean; update: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Vendor Partners', href: '/admin/vendor-partners' },
];

const filters = reactive({ search: props.filters.search ?? '', status: props.filters.status ?? '' });
let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(() => {
        const q: Record<string, string> = {};
        if (filters.search) q.search = filters.search;
        if (filters.status) q.status = filters.status;
        router.get('/admin/vendor-partners', q, { preserveState: true, replace: true });
    }, 350);
});

function setStatus(partner: Partner, status: string) {
    router.post(`/admin/vendor-partners/${partner.id}/status`, { status }, { preserveScroll: true });
}

const statusStyle: Record<string, string> = {
    pending_activation: 'bg-brand-orange/15 text-brand-orange',
    active: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
    suspended: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <Head title="Vendor Partners" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-semibold">Vendor Partners</h1>
                    <p class="text-sm text-muted-foreground">Add sourcing partners and activate them so they can submit vehicles.</p>
                </div>
                <Button v-if="can.create" as-child>
                    <Link href="/admin/vendor-partners/create">
                        <Plus class="size-4" />
                        Add Partner
                    </Link>
                </Button>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="relative w-full max-w-xs">
                    <Search class="absolute left-2.5 top-2.5 size-4 text-muted-foreground" />
                    <Input v-model="filters.search" placeholder="Name, company, email…" class="pl-9" />
                </div>
                <select v-model="filters.status" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                    <option value="">All statuses</option>
                    <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
                </select>
            </div>

            <div class="overflow-x-auto rounded-xl border border-sidebar-border/70 dark:border-sidebar-border">
                <table class="w-full min-w-[880px] text-sm">
                    <thead>
                        <tr class="border-b bg-muted/50 text-left">
                            <th class="px-4 py-3 font-medium">Partner</th>
                            <th class="px-4 py-3 font-medium">Contact</th>
                            <th class="px-4 py-3 font-medium">GST</th>
                            <th class="px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 text-right font-medium">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="partners.data.length === 0">
                            <td colspan="5" class="px-4 py-10 text-center text-muted-foreground">No vendor partners.</td>
                        </tr>
                        <tr v-for="p in partners.data" :key="p.id" class="border-b last:border-0">
                            <td class="px-4 py-3">
                                <div class="font-medium">{{ p.company_name ?? p.name }}</div>
                                <div class="text-xs text-muted-foreground">
                                    {{ p.name }}<span v-if="p.city"> · {{ p.city }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3">
                                <div>{{ p.phone ?? '—' }}</div>
                                <div class="text-xs text-muted-foreground">{{ p.email }}</div>
                            </td>
                            <td class="px-4 py-3">{{ p.gst_number ?? '—' }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[p.status]">{{
                                    p.status_label
                                }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-1.5">
                                    <template v-if="can.activate">
                                        <Button v-if="p.status !== 'active'" size="sm" @click="setStatus(p, 'active')">Activate</Button>
                                        <Button
                                            v-if="p.status === 'pending_activation'"
                                            size="sm"
                                            variant="destructive"
                                            @click="setStatus(p, 'rejected')"
                                            >Reject</Button
                                        >
                                        <Button v-if="p.status === 'active'" size="sm" variant="outline" @click="setStatus(p, 'suspended')"
                                            >Suspend</Button
                                        >
                                    </template>
                                    <Button v-if="can.update" size="sm" variant="ghost" as-child>
                                        <Link :href="`/admin/vendor-partners/${p.id}/edit`">Edit</Link>
                                    </Button>
                                    <span v-if="!can.activate && !can.update" class="text-xs text-muted-foreground">—</span>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <Pagination :paginator="partners" />
        </div>
    </AppLayout>
</template>
