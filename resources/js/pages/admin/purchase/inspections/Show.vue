<script setup lang="ts">
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

interface Section {
    id: number;
    key: string;
    label: string;
    rating: number | null;
    status: string | null;
    remarks: string | null;
    repair_estimate: string;
    items: { id: number; label: string; value: string; severity: string | null }[];
}

const props = defineProps<{
    inspection: {
        id: number;
        inspection_number: string;
        status: string;
        result: string | null;
        overall_grade: string | null;
        odometer_km: number | null;
        remarks: string | null;
        locked_at: string | null;
        total_repair_estimate: string;
        purchase_lead?: Record<string, any>;
        inspector?: { id: number; name: string } | null;
        sections: Section[];
        media: { id: number; type: string; file_path: string; thumbnail_path: string | null }[];
    };
    can: { edit: boolean; review: boolean };
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Inspections', href: '/admin/inspections' },
    { title: props.inspection.inspection_number, href: '#' },
];

const locked = computed(() => props.inspection.locked_at !== null);

const form = useForm({
    odometer_km: props.inspection.odometer_km,
    overall_grade: props.inspection.overall_grade ?? '',
    remarks: props.inspection.remarks ?? '',
    sections: props.inspection.sections.map((s) => ({
        id: s.id,
        rating: s.rating,
        status: s.status ?? 'na',
        remarks: s.remarks,
        repair_estimate: Number(s.repair_estimate),
    })),
});

const submitForm = useForm({ result: props.inspection.result ?? 'recommended' });
const mediaForm = useForm<{ file: File | null; category: string }>({ file: null, category: '' });

const totalRepair = computed(() => form.sections.reduce((sum, s) => sum + Number(s.repair_estimate || 0), 0));

function save() {
    form.put(`/admin/inspections/${props.inspection.id}`, { preserveScroll: true });
}
function submit() {
    submitForm.post(`/admin/inspections/${props.inspection.id}/submit`, { preserveScroll: true });
}
function uploadMedia() {
    mediaForm.post(`/admin/inspections/${props.inspection.id}/media`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => mediaForm.reset(),
    });
}
</script>

<template>
    <Head :title="inspection.inspection_number" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full flex-1 flex-col gap-4 p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <div class="flex items-center gap-3">
                        <h1 class="text-xl font-semibold">{{ inspection.inspection_number }}</h1>
                        <span class="rounded-full bg-muted px-2.5 py-0.5 text-xs font-medium capitalize">{{
                            inspection.status.replace('_', ' ')
                        }}</span>
                        <span
                            v-if="locked"
                            class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/40 dark:text-green-400"
                            >Locked</span
                        >
                    </div>
                    <p class="mt-1 text-sm text-muted-foreground">
                        <Link v-if="inspection.purchase_lead" :href="`/admin/purchase-leads/${inspection.purchase_lead.id}`" class="underline">
                            {{ inspection.purchase_lead.lead_number }}
                        </Link>
                        · {{ [inspection.purchase_lead?.make, inspection.purchase_lead?.model].filter(Boolean).join(' ') }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <Button variant="outline" as-child><Link href="/admin/inspections">Back</Link></Button>
                    <Button v-if="can.edit && !locked" :disabled="form.processing" @click="save">Save Progress</Button>
                </div>
            </div>

            <Card>
                <CardHeader><CardTitle>Overview</CardTitle></CardHeader>
                <CardContent class="flex flex-wrap items-end gap-4">
                    <div class="grid gap-1">
                        <Label class="text-xs">Odometer (km)</Label>
                        <Input v-model.number="form.odometer_km" type="number" min="0" class="h-9 w-36" :disabled="locked" />
                    </div>
                    <div class="grid gap-1">
                        <Label class="text-xs">Overall Grade</Label>
                        <select
                            v-model="form.overall_grade"
                            :disabled="locked"
                            class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm"
                        >
                            <option value="">—</option>
                            <option>A</option>
                            <option>B</option>
                            <option>C</option>
                            <option>D</option>
                        </select>
                    </div>
                    <div class="grid flex-1 gap-1">
                        <Label class="text-xs">Remarks</Label>
                        <Input v-model="form.remarks" class="h-9" :disabled="locked" />
                    </div>
                    <div class="rounded-lg bg-muted/50 px-4 py-2 text-center">
                        <p class="text-xs text-muted-foreground">Total Repair Est.</p>
                        <p class="text-lg font-bold">₹{{ totalRepair.toLocaleString('en-IN') }}</p>
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Checklist Sections</CardTitle></CardHeader>
                <CardContent class="grid gap-3">
                    <div v-for="(section, idx) in form.sections" :key="section.id" class="grid gap-2 rounded-lg border p-3">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-medium capitalize">{{ inspection.sections[idx].label }}</span>
                            <div class="flex items-center gap-2">
                                <select
                                    v-model="section.status"
                                    :disabled="locked"
                                    class="h-8 rounded-md border border-input bg-transparent px-2 text-xs shadow-sm"
                                >
                                    <option value="pass">Pass</option>
                                    <option value="fail">Fail</option>
                                    <option value="na">N/A</option>
                                </select>
                                <select
                                    v-model.number="section.rating"
                                    :disabled="locked"
                                    class="h-8 rounded-md border border-input bg-transparent px-2 text-xs shadow-sm"
                                >
                                    <option :value="null">Rating</option>
                                    <option v-for="n in 5" :key="n" :value="n">{{ n }}/5</option>
                                </select>
                                <Input
                                    v-model.number="section.repair_estimate"
                                    type="number"
                                    min="0"
                                    placeholder="Repair ₹"
                                    class="h-8 w-28"
                                    :disabled="locked"
                                />
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-1 text-xs text-muted-foreground">
                            <span
                                v-for="item in inspection.sections[idx].items"
                                :key="item.id"
                                class="rounded-full px-2 py-0.5"
                                :class="item.severity === 'critical' ? 'bg-red-50 text-red-600 dark:bg-red-950/40' : 'bg-muted'"
                            >
                                {{ item.label }}
                            </span>
                        </div>
                        <Input v-model="section.remarks" placeholder="Section remarks" class="h-8" :disabled="locked" />
                    </div>
                </CardContent>
            </Card>

            <Card>
                <CardHeader><CardTitle>Media</CardTitle></CardHeader>
                <CardContent class="grid gap-3">
                    <div v-if="can.edit && !locked" class="flex items-end gap-2">
                        <input
                            type="file"
                            accept=".jpg,.jpeg,.png,.mp4,.mov"
                            class="text-sm"
                            @input="mediaForm.file = ($event.target as HTMLInputElement).files?.[0] ?? null"
                        />
                        <Input v-model="mediaForm.category" placeholder="Category (optional)" class="h-9 w-48" />
                        <Button size="sm" :disabled="!mediaForm.file || mediaForm.processing" @click="uploadMedia">Upload</Button>
                    </div>
                    <p v-if="!inspection.media.length" class="text-sm text-muted-foreground">No media uploaded.</p>
                    <div v-else class="flex flex-wrap gap-2">
                        <span v-for="m in inspection.media" :key="m.id" class="rounded-md bg-muted px-2 py-1 text-xs">
                            {{ m.type }} #{{ m.id }}
                        </span>
                    </div>
                </CardContent>
            </Card>

            <Card v-if="can.edit && !locked">
                <CardHeader><CardTitle>Submit &amp; Lock</CardTitle></CardHeader>
                <CardContent class="flex flex-wrap items-end gap-3">
                    <div class="grid gap-1">
                        <Label class="text-xs">Recommendation</Label>
                        <select v-model="submitForm.result" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option value="recommended">Recommended</option>
                            <option value="recommended_with_repairs">Recommended with repairs</option>
                            <option value="management_approval">Management approval required</option>
                            <option value="not_recommended">Not recommended</option>
                        </select>
                    </div>
                    <Button :disabled="submitForm.processing" @click="submit">Submit &amp; Lock Inspection</Button>
                    <p class="text-xs text-muted-foreground">Save progress first — submitting locks the inspection from further edits.</p>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
