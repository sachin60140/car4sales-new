<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import VendorLayout from '@/layouts/VendorLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { Clock, FileCheck2, FilePlus2, FileX2, Files, ShieldCheck } from 'lucide-vue-next';
import { computed } from 'vue';

interface Recent {
    id: number;
    submission_number: string;
    title: string;
    expected_amount: string;
    status: string;
    status_label: string;
    created_at: string;
}

const props = defineProps<{
    profile: { company_name: string | null; contact_person: string | null; status: string; status_label: string; is_active: boolean };
    stats: { draft: number; pending_review: number; approved: number; rejected: number };
    kyc: { status: string; required_total: number; required_verified: number; required_uploaded: number };
    recent: Recent[];
}>();

const kycPct = computed(() => (props.kyc.required_total ? Math.round((props.kyc.required_verified / props.kyc.required_total) * 100) : 0));
const kycStatusStyle: Record<string, string> = {
    pending: 'bg-brand-orange/15 text-brand-orange',
    submitted: 'bg-blue-100 text-blue-700 dark:bg-blue-900/40 dark:text-blue-400',
    verified: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
};

const tiles = [
    { key: 'draft', label: 'Drafts', icon: Files, accent: 'text-muted-foreground' },
    { key: 'pending_review', label: 'In Review', icon: Clock, accent: 'text-brand-orange' },
    { key: 'approved', label: 'Approved', icon: FileCheck2, accent: 'text-emerald-600' },
    { key: 'rejected', label: 'Rejected', icon: FileX2, accent: 'text-brand-red' },
] as const;

function money(v: string): string {
    return '₹' + Number(v).toLocaleString('en-IN');
}

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    pending_review: 'bg-brand-orange/15 text-brand-orange',
    approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
};
</script>

<template>
    <Head title="Vendor Dashboard" />

    <VendorLayout>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">Welcome{{ profile.company_name ? ', ' + profile.company_name : '' }}</h1>
                <p class="text-sm text-muted-foreground">Submit vehicles you'd like to sell to us and track their review.</p>
            </div>
            <Button v-if="profile.is_active" as-child>
                <Link href="/vendor/submissions/create"><FilePlus2 class="mr-1 size-4" /> New Submission</Link>
            </Button>
        </div>

        <!-- KYC completeness -->
        <Card class="mt-5" :class="kyc.status !== 'verified' ? 'border-brand-orange/40 bg-brand-orange/5' : ''">
            <CardContent class="p-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <ShieldCheck class="size-5 shrink-0" :class="kyc.status === 'verified' ? 'text-emerald-600' : 'text-brand-orange'" />
                        <div>
                            <p class="font-medium">KYC verification</p>
                            <p class="text-sm text-muted-foreground">
                                {{ kyc.required_verified }} of {{ kyc.required_total }} required documents verified
                                <span v-if="kyc.required_uploaded > kyc.required_verified">
                                    · {{ kyc.required_uploaded - kyc.required_verified }} awaiting review
                                </span>
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium capitalize" :class="kycStatusStyle[kyc.status]">
                            KYC {{ kyc.status }}
                        </span>
                        <Button variant="outline" size="sm" as-child>
                            <Link href="/vendor/kyc">{{ kyc.status === 'verified' ? 'View KYC' : 'Complete KYC' }}</Link>
                        </Button>
                    </div>
                </div>
                <div class="mt-3 h-2 w-full overflow-hidden rounded-full bg-muted">
                    <div
                        class="h-2 rounded-full transition-all"
                        :class="kyc.status === 'verified' ? 'bg-emerald-500' : 'bg-brand-orange'"
                        :style="{ width: kycPct + '%' }"
                    />
                </div>
            </CardContent>
        </Card>

        <!-- Activation gate -->
        <Card v-if="!profile.is_active" class="mt-4 border-brand-orange/40 bg-brand-orange/5">
            <CardContent class="flex items-start gap-3 p-4">
                <Clock class="mt-0.5 size-5 shrink-0 text-brand-orange" />
                <div>
                    <p class="font-medium">Account {{ profile.status_label }}</p>
                    <p class="text-sm text-muted-foreground">
                        <template v-if="kyc.status !== 'verified'">
                            Complete your KYC documents above so our team can verify and activate your account. You can submit vehicles once active.
                        </template>
                        <template v-else>
                            Your KYC is verified — your account is awaiting final activation by our team. We'll notify you once it's approved.
                        </template>
                    </p>
                </div>
            </CardContent>
        </Card>

        <template v-else>
            <div class="mt-5 grid grid-cols-2 gap-3 sm:grid-cols-4">
                <Card v-for="t in tiles" :key="t.key">
                    <CardContent class="p-4">
                        <div class="flex items-center justify-between">
                            <p class="text-xs text-muted-foreground">{{ t.label }}</p>
                            <component :is="t.icon" class="size-4" :class="t.accent" />
                        </div>
                        <p class="mt-1 text-2xl font-bold">{{ stats[t.key] }}</p>
                    </CardContent>
                </Card>
            </div>

            <div class="mt-5">
                <div class="mb-2 flex items-center justify-between">
                    <h2 class="text-sm font-semibold">Recent submissions</h2>
                    <Link href="/vendor/submissions" class="text-sm text-brand-red hover:underline">View all</Link>
                </div>
                <div class="overflow-hidden rounded-xl border border-sidebar-border/70">
                    <div v-if="recent.length === 0" class="px-4 py-10 text-center text-sm text-muted-foreground">
                        No submissions yet. Click "New Submission" to get started.
                    </div>
                    <ul v-else class="divide-y">
                        <li v-for="s in recent" :key="s.id">
                            <Link
                                :href="`/vendor/submissions/${s.id}`"
                                class="flex items-center justify-between gap-3 px-4 py-3 transition hover:bg-muted/40"
                            >
                                <div>
                                    <p class="font-medium">{{ s.title }}</p>
                                    <p class="text-xs text-muted-foreground">{{ s.submission_number }} · {{ s.created_at }}</p>
                                </div>
                                <div class="flex items-center gap-3">
                                    <span class="text-sm font-semibold">{{ money(s.expected_amount) }}</span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[s.status]">{{
                                        s.status_label
                                    }}</span>
                                </div>
                            </Link>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </VendorLayout>
</template>
