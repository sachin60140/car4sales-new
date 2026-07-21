<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import AreaChart from '@/components/charts/AreaChart.vue';
import BarChart from '@/components/charts/BarChart.vue';
import DoughnutChart from '@/components/charts/DoughnutChart.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { usePermissions } from '@/composables/usePermissions';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, LoginHistoryEntry } from '@/types';
import { Head, Link } from '@inertiajs/vue3';
import {
    BookMarked,
    Building2,
    Car,
    ClipboardCheck,
    FileCheck2,
    LayoutGrid,
    Network,
    PhoneCall,
    Plus,
    ShoppingCart,
    Truck,
    Users,
    UsersRound,
    Wallet,
    type LucideIcon,
} from 'lucide-vue-next';
import { computed } from 'vue';

interface Stat {
    key: string;
    label: string;
    value: number;
    icon: string;
    accent: 'yellow' | 'orange' | 'red' | 'maroon' | 'gold';
    href: string;
}

interface Series {
    label: string;
    value: number;
}

const props = defineProps<{
    greeting: { name: string; roles: string[]; branch: string | null };
    stats: Stat[];
    charts: { pipeline?: Series[]; leadTrend?: Series[]; stockByStatus?: Series[]; revenueTrend?: Series[] };
    recentLogins: LoginHistoryEntry[];
}>();

const { can } = usePermissions();

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

const icons: Record<string, LucideIcon> = {
    ShoppingCart,
    PhoneCall,
    ClipboardCheck,
    Car,
    Users,
    Building2,
    Network,
    UsersRound,
    LayoutGrid,
    BookMarked,
    Truck,
    FileCheck2,
    Wallet,
};

const accentChip: Record<Stat['accent'], string> = {
    yellow: 'bg-brand-yellow/20 text-brand-maroon dark:text-brand-yellow',
    orange: 'bg-brand-orange/15 text-brand-orange',
    red: 'bg-brand-red/15 text-brand-red',
    maroon: 'bg-brand-maroon/15 text-brand-maroon dark:bg-brand-red/20 dark:text-brand-red',
    gold: 'bg-brand-gold/20 text-brand-orange',
};

const accentBar: Record<Stat['accent'], string> = {
    yellow: 'bg-brand-yellow',
    orange: 'bg-brand-orange',
    red: 'bg-brand-red',
    maroon: 'bg-brand-maroon dark:bg-brand-red',
    gold: 'bg-brand-gold',
};

const greetingTime = computed(() => {
    const h = new Date().getHours();
    if (h < 12) return 'Good morning';
    if (h < 17) return 'Good afternoon';
    return 'Good evening';
});

const quickActions = computed(() =>
    [
        { label: 'New Purchase Lead', href: '/admin/purchase-leads/create', permission: 'purchase-leads.create', icon: Plus },
        { label: 'Purchase Leads', href: '/admin/purchase-leads', permission: 'purchase-leads.view', icon: ShoppingCart },
        { label: 'Inspections', href: '/admin/inspections', permission: 'inspections.view', icon: ClipboardCheck },
        { label: 'Employees', href: '/admin/employees', permission: 'employees.view', icon: Users },
    ].filter((a) => can(a.permission)),
);

const pipeline = computed(() => props.charts.pipeline ?? []);
const trend = computed(() => props.charts.leadTrend ?? []);
const stock = computed(() => props.charts.stockByStatus ?? []);
const revenue = computed(() => props.charts.revenueTrend ?? []);
const hasRevenue = computed(() => revenue.value.some((d) => d.value !== 0));

const hasCharts = computed(() => pipeline.value.length > 0 || trend.value.length > 0 || stock.value.length > 0 || hasRevenue.value);

function formatDate(value: string): string {
    return new Date(value).toLocaleString();
}
</script>

<template>
    <Head title="Dashboard" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-5 p-4 md:p-5">
            <!-- Hero -->
            <section
                class="relative overflow-hidden rounded-2xl bg-gradient-to-br from-brand-maroon to-[hsl(0_62%_15%)] p-6 text-white shadow-lg md:p-8"
            >
                <AppLogoIcon class="pointer-events-none absolute -right-6 -top-4 size-56 text-brand-yellow/10" />
                <div class="relative z-10 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-brand-yellow">{{ greetingTime }},</p>
                        <h1 class="mt-1 text-2xl font-extrabold tracking-tight md:text-3xl">{{ greeting.name }}</h1>
                        <div class="mt-3 flex flex-wrap items-center gap-2">
                            <span
                                v-for="role in greeting.roles"
                                :key="role"
                                class="rounded-full bg-white/15 px-2.5 py-0.5 text-xs font-medium backdrop-blur"
                            >
                                {{ role }}
                            </span>
                            <span v-if="greeting.branch" class="rounded-full bg-brand-yellow px-2.5 py-0.5 text-xs font-semibold text-brand-maroon">
                                {{ greeting.branch }}
                            </span>
                        </div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <Button
                            v-for="action in quickActions"
                            :key="action.label"
                            :variant="action.icon === Plus ? 'default' : 'secondary'"
                            size="sm"
                            as-child
                            :class="
                                action.icon === Plus
                                    ? 'bg-brand-yellow text-brand-maroon hover:bg-brand-yellow/90'
                                    : 'bg-white/15 text-white hover:bg-white/25'
                            "
                        >
                            <Link :href="action.href"><component :is="action.icon" class="mr-1 size-4" /> {{ action.label }}</Link>
                        </Button>
                    </div>
                </div>
            </section>

            <!-- KPI cards -->
            <section v-if="stats.length" class="grid grid-cols-2 gap-4 md:grid-cols-3 xl:grid-cols-4">
                <component
                    :is="stat.href !== '#' ? Link : 'div'"
                    v-for="stat in stats"
                    :key="stat.key"
                    :href="stat.href !== '#' ? stat.href : undefined"
                    class="group relative overflow-hidden rounded-xl border bg-card p-4 shadow-sm transition-all hover:-translate-y-0.5 hover:shadow-md"
                >
                    <span class="absolute inset-y-0 left-0 w-1" :class="accentBar[stat.accent]" />
                    <div class="flex items-start justify-between">
                        <div>
                            <p class="text-sm font-medium text-muted-foreground">{{ stat.label }}</p>
                            <p class="mt-1 text-3xl font-bold tabular-nums">{{ stat.value.toLocaleString() }}</p>
                        </div>
                        <span class="flex size-11 items-center justify-center rounded-lg" :class="accentChip[stat.accent]">
                            <component :is="icons[stat.icon] ?? LayoutGrid" class="size-5" />
                        </span>
                    </div>
                </component>
            </section>

            <!-- Charts -->
            <section v-if="hasCharts" class="grid gap-4 lg:grid-cols-3">
                <Card v-if="trend.length" class="lg:col-span-2">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">New Purchase Leads</CardTitle>
                        <span class="text-xs text-muted-foreground">Last 14 days</span>
                    </CardHeader>
                    <CardContent>
                        <AreaChart :labels="trend.map((d) => d.label)" :values="trend.map((d) => d.value)" label="Leads" />
                    </CardContent>
                </Card>

                <Card v-if="pipeline.length">
                    <CardHeader><CardTitle class="text-base">Lead Pipeline</CardTitle></CardHeader>
                    <CardContent>
                        <DoughnutChart :labels="pipeline.map((d) => d.label)" :values="pipeline.map((d) => d.value)" />
                    </CardContent>
                </Card>

                <Card v-if="stock.length" :class="trend.length ? 'lg:col-span-3' : 'lg:col-span-2'">
                    <CardHeader><CardTitle class="text-base">Stock by Status</CardTitle></CardHeader>
                    <CardContent>
                        <BarChart :labels="stock.map((d) => d.label)" :values="stock.map((d) => d.value)" />
                    </CardContent>
                </Card>

                <Card v-if="hasRevenue" class="lg:col-span-3">
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle class="text-base">Collections</CardTitle>
                        <span class="text-xs text-muted-foreground">Net received · last 14 days</span>
                    </CardHeader>
                    <CardContent>
                        <BarChart :labels="revenue.map((d) => d.label)" :values="revenue.map((d) => d.value)" />
                    </CardContent>
                </Card>
            </section>

            <!-- Recent activity -->
            <Card v-if="recentLogins.length">
                <CardHeader><CardTitle class="text-base">Recent Sign-in Activity</CardTitle></CardHeader>
                <CardContent>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="border-b text-left text-muted-foreground">
                                    <th class="py-2 pr-4 font-medium">When</th>
                                    <th class="py-2 pr-4 font-medium">User</th>
                                    <th class="py-2 pr-4 font-medium">Event</th>
                                    <th class="py-2 font-medium">IP</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="entry in recentLogins" :key="entry.id" class="border-b last:border-0">
                                    <td class="whitespace-nowrap py-2 pr-4">{{ formatDate(entry.created_at) }}</td>
                                    <td class="py-2 pr-4">{{ entry.user?.name ?? entry.email ?? '—' }}</td>
                                    <td class="py-2 pr-4">
                                        <span
                                            class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize"
                                            :class="
                                                entry.event === 'failed'
                                                    ? 'bg-brand-red/15 text-brand-red'
                                                    : entry.event === 'login'
                                                      ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400'
                                                      : 'bg-muted text-muted-foreground'
                                            "
                                        >
                                            {{ entry.event.replace('_', ' ') }}
                                        </span>
                                    </td>
                                    <td class="py-2 font-mono text-xs">{{ entry.ip_address ?? '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </CardContent>
            </Card>

            <!-- Empty state -->
            <Card v-if="!stats.length && !hasCharts">
                <CardContent class="py-12 text-center text-muted-foreground">
                    Welcome to Car4Sales. Your dashboard will populate as modules are assigned to your role.
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
