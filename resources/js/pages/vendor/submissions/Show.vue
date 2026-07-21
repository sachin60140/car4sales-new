<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import VendorLayout from '@/layouts/VendorLayout.vue';
import { Head, Link } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{ submission: Record<string, any> }>();
const s = computed(() => props.submission);

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const statusStyle: Record<string, string> = {
    draft: 'bg-muted text-muted-foreground',
    pending_review: 'bg-brand-orange/15 text-brand-orange',
    approved: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    rejected: 'bg-brand-red/15 text-brand-red',
};
const resultStyle: Record<string, string> = {
    pass: 'text-emerald-600',
    fail: 'text-brand-red',
    na: 'text-muted-foreground',
};
</script>

<template>
    <Head :title="s.submission_number" />

    <VendorLayout>
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ s.make }} {{ s.model }} {{ s.variant }}</h1>
                    <span class="rounded-full px-2 py-0.5 text-xs font-medium" :class="statusStyle[s.status]">{{ s.status_label }}</span>
                </div>
                <p class="text-sm text-muted-foreground">{{ s.submission_number }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Button v-if="s.editable" as-child><Link :href="`/vendor/submissions/${s.id}/edit`">Edit</Link></Button>
                <Button variant="outline" as-child><Link href="/vendor/submissions">Back</Link></Button>
            </div>
        </div>

        <!-- Review outcome banners -->
        <Card v-if="s.status === 'approved'" class="mt-4 border-emerald-500/40 bg-emerald-500/5">
            <CardContent class="p-4 text-sm">
                <p class="font-medium text-emerald-700 dark:text-emerald-400">Approved</p>
                <p class="text-muted-foreground">Your vehicle has been accepted into our purchase process<span v-if="s.review_remarks"> — {{ s.review_remarks }}</span>.</p>
            </CardContent>
        </Card>
        <Card v-else-if="s.status === 'rejected'" class="mt-4 border-brand-red/40 bg-brand-red/5">
            <CardContent class="p-4 text-sm">
                <p class="font-medium text-brand-red">Rejected</p>
                <p class="text-muted-foreground">{{ s.review_remarks ?? 'This submission was not accepted.' }} You can edit and resubmit.</p>
            </CardContent>
        </Card>

        <div class="mt-4 grid gap-4 lg:grid-cols-3">
            <div class="space-y-4 lg:col-span-2">
                <Card>
                    <CardHeader><CardTitle class="text-base">Vehicle</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-y-2 text-sm sm:grid-cols-3">
                        <span class="text-muted-foreground">Year</span><span class="sm:col-span-2">{{ s.manufacturing_year ?? '—' }}</span>
                        <span class="text-muted-foreground">Reg. No.</span><span class="sm:col-span-2">{{ s.registration_number ?? '—' }}</span>
                        <span class="text-muted-foreground">Fuel / Trans.</span><span class="sm:col-span-2">{{ s.fuel_type ?? '—' }} / {{ s.transmission ?? '—' }}</span>
                        <span class="text-muted-foreground">Odometer</span><span class="sm:col-span-2">{{ s.odometer_km ? Number(s.odometer_km).toLocaleString('en-IN') + ' km' : '—' }}</span>
                        <span class="text-muted-foreground">Colour</span><span class="sm:col-span-2">{{ s.color ?? '—' }}</span>
                        <span class="text-muted-foreground">Owner No.</span><span class="sm:col-span-2">{{ s.ownership_serial ?? '—' }}</span>
                    </CardContent>
                </Card>

                <Card v-if="s.items?.length">
                    <CardHeader><CardTitle class="text-base">Condition Report</CardTitle></CardHeader>
                    <CardContent>
                        <ul class="divide-y text-sm">
                            <li v-for="it in s.items" :key="it.id" class="flex items-center justify-between gap-2 py-2">
                                <span>{{ it.label }} <span class="text-xs text-muted-foreground">· {{ it.section }}</span><span v-if="it.remarks" class="text-xs text-muted-foreground"> — {{ it.remarks }}</span></span>
                                <span class="flex items-center gap-2">
                                    <span v-if="it.rating" class="text-xs text-muted-foreground">{{ it.rating }}★</span>
                                    <span class="font-medium uppercase" :class="resultStyle[it.result]">{{ it.result === 'na' ? 'N/A' : it.result }}</span>
                                </span>
                            </li>
                        </ul>
                    </CardContent>
                </Card>

                <Card v-if="s.gallery?.length">
                    <CardHeader><CardTitle class="text-base">Photos</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <a v-for="m in s.gallery" :key="m.id" :href="m.url" target="_blank" class="overflow-hidden rounded-lg border">
                            <img :src="m.url" alt="" class="aspect-video w-full object-cover" />
                        </a>
                    </CardContent>
                </Card>

                <Card v-if="s.damage?.length">
                    <CardHeader><CardTitle class="text-base">Damaged Parts</CardTitle></CardHeader>
                    <CardContent class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                        <a v-for="m in s.damage" :key="m.id" :href="m.url" target="_blank" class="overflow-hidden rounded-lg border">
                            <img :src="m.url" alt="" class="aspect-video w-full object-cover" />
                        </a>
                    </CardContent>
                </Card>
            </div>

            <div class="space-y-4">
                <Card>
                    <CardContent class="p-4">
                        <p class="text-xs text-muted-foreground">Expected Amount</p>
                        <p class="text-2xl font-bold">{{ money(s.expected_amount) }}</p>
                        <p v-if="s.overall_rating" class="mt-1 text-sm text-muted-foreground">Overall: {{ s.overall_rating }}★</p>
                        <p v-if="s.overall_remark" class="mt-2 text-sm">{{ s.overall_remark }}</p>
                    </CardContent>
                </Card>
                <Card v-if="s.branch">
                    <CardContent class="p-4 text-sm">
                        <p class="text-xs text-muted-foreground">Preferred Branch</p>
                        <p class="font-medium">{{ s.branch.name }}</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </VendorLayout>
</template>
