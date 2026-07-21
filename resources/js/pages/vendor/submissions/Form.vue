<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import VendorLayout from '@/layouts/VendorLayout.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { Trash2, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';

interface Item { section: string; label: string; result: string; rating: number | null; remarks: string; [key: string]: string | number | null }
interface Media { id: number; caption: string | null; url: string }

const props = defineProps<{
    submission: Record<string, any> | null;
    branches: { id: number; name: string }[];
    resultOptions: { value: string; label: string }[];
    checklistTemplate: { section: string; label: string }[];
}>();

const isEdit = computed(() => props.submission !== null);

const initialItems: Item[] = props.submission?.items?.length
    ? props.submission.items.map((i: any) => ({ section: i.section, label: i.label, result: i.result, rating: i.rating, remarks: i.remarks ?? '' }))
    : props.checklistTemplate.map((t) => ({ section: t.section, label: t.label, result: 'na', rating: null, remarks: '' }));

const form = useForm<{
    make: string;
    model: string;
    variant: string;
    manufacturing_year: number | null;
    registration_number: string;
    registration_state: string;
    fuel_type: string;
    transmission: string;
    color: string;
    odometer_km: number | null;
    ownership_serial: number | null;
    expected_amount: number | null;
    overall_remark: string;
    branch_id: number | string;
    items: Item[];
}>({
    make: props.submission?.make ?? '',
    model: props.submission?.model ?? '',
    variant: props.submission?.variant ?? '',
    manufacturing_year: props.submission?.manufacturing_year ?? null,
    registration_number: props.submission?.registration_number ?? '',
    registration_state: props.submission?.registration_state ?? '',
    fuel_type: props.submission?.fuel_type ?? '',
    transmission: props.submission?.transmission ?? '',
    color: props.submission?.color ?? '',
    odometer_km: props.submission?.odometer_km ?? null,
    ownership_serial: props.submission?.ownership_serial ?? null,
    expected_amount: props.submission?.expected_amount ?? null,
    overall_remark: props.submission?.overall_remark ?? '',
    branch_id: props.submission?.branch_id ?? '',
    items: initialItems,
});

const fuels = ['Petrol', 'Diesel', 'CNG', 'Electric', 'Hybrid'];
const transmissions = ['Manual', 'Automatic'];

// Overall rating is auto-calculated (read-only): the average of the checklist
// items that carry a rating. The server recomputes this authoritatively on save.
const overallRating = computed<number | null>(() => {
    const rated = form.items.filter((i) => i.rating != null);
    if (rated.length === 0) return null;
    return Math.round(rated.reduce((sum, i) => sum + Number(i.rating), 0) / rated.length);
});

function save() {
    if (isEdit.value) {
        form.put(`/vendor/submissions/${props.submission!.id}`, { preserveScroll: true });
    } else {
        form.post('/vendor/submissions');
    }
}

// --- Image uploads (edit mode) ---
const gallery = computed<Media[]>(() => props.submission?.gallery ?? []);
const damage = computed<Media[]>(() => props.submission?.damage ?? []);
const uploading = ref<'gallery' | 'damage' | null>(null);

// Submit gates (mirror the server rules): at least one vehicle photo, and a
// damage photo whenever any checklist item was marked "fail".
const hasFail = computed<boolean>(() => (props.submission?.items ?? []).some((i: Item) => i.result === 'fail'));
const needsDamagePhoto = computed(() => hasFail.value && damage.value.length === 0);
const submitBlockedReason = computed<string | null>(() => {
    if (gallery.value.length === 0) return 'Upload at least one vehicle photo to submit.';
    if (needsDamagePhoto.value) return 'You marked an item as failed — add a photo of the damage to submit.';
    return null;
});

function pickAndUpload(type: 'gallery' | 'damage', event: Event) {
    const input = event.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    uploading.value = type;
    router.post(`/vendor/submissions/${props.submission!.id}/media`, { file, type }, {
        preserveScroll: true,
        forceFormData: true,
        onFinish: () => {
            uploading.value = null;
            input.value = '';
        },
    });
}

function removeImage(id: number) {
    router.delete(`/vendor/submission-media/${id}`, { preserveScroll: true });
}

function submitForReview() {
    router.post(`/vendor/submissions/${props.submission!.id}/submit`);
}

const resultStyle: Record<string, string> = {
    pass: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/40 dark:text-emerald-400',
    fail: 'bg-brand-red/15 text-brand-red',
    na: 'bg-muted text-muted-foreground',
};
</script>

<template>
    <Head :title="isEdit ? 'Edit Submission' : 'New Submission'" />

    <VendorLayout>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold">{{ isEdit ? 'Edit Submission' : 'New Vehicle Submission' }}</h1>
                <p v-if="isEdit" class="text-sm text-muted-foreground">{{ submission!.submission_number }}</p>
            </div>
            <Button variant="outline" as-child><Link href="/vendor/submissions">Back</Link></Button>
        </div>

        <div class="mt-4 grid gap-4">
            <!-- Vehicle details -->
            <Card>
                <CardHeader><CardTitle class="text-base">Vehicle Details</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="grid gap-1.5"><Label>Make *</Label><Input v-model="form.make" /><InputError :message="form.errors.make" /></div>
                    <div class="grid gap-1.5"><Label>Model *</Label><Input v-model="form.model" /><InputError :message="form.errors.model" /></div>
                    <div class="grid gap-1.5"><Label>Variant</Label><Input v-model="form.variant" /></div>
                    <div class="grid gap-1.5"><Label>Mfg. Year</Label><Input v-model.number="form.manufacturing_year" type="number" /><InputError :message="form.errors.manufacturing_year" /></div>
                    <div class="grid gap-1.5"><Label>Registration No.</Label><Input v-model="form.registration_number" /></div>
                    <div class="grid gap-1.5"><Label>Registration State</Label><Input v-model="form.registration_state" placeholder="e.g. UP" /></div>
                    <div class="grid gap-1.5">
                        <Label>Fuel</Label>
                        <select v-model="form.fuel_type" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option value="">—</option>
                            <option v-for="f in fuels" :key="f" :value="f">{{ f }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Transmission</Label>
                        <select v-model="form.transmission" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option value="">—</option>
                            <option v-for="t in transmissions" :key="t" :value="t">{{ t }}</option>
                        </select>
                    </div>
                    <div class="grid gap-1.5"><Label>Colour</Label><Input v-model="form.color" /></div>
                    <div class="grid gap-1.5"><Label>Odometer (km)</Label><Input v-model.number="form.odometer_km" type="number" /></div>
                    <div class="grid gap-1.5"><Label>Owner No.</Label><Input v-model.number="form.ownership_serial" type="number" min="1" /></div>
                    <div class="grid gap-1.5">
                        <Label>Preferred Branch</Label>
                        <select v-model="form.branch_id" class="h-9 rounded-md border border-input bg-transparent px-3 text-sm shadow-sm">
                            <option value="">No preference</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                </CardContent>
            </Card>

            <!-- Condition checklist -->
            <Card>
                <CardHeader><CardTitle class="text-base">Condition Report</CardTitle></CardHeader>
                <CardContent class="space-y-2">
                    <div class="hidden grid-cols-12 gap-2 px-1 text-xs font-medium uppercase text-muted-foreground sm:grid">
                        <span class="col-span-4">Item</span>
                        <span class="col-span-3">Result</span>
                        <span class="col-span-2">Rating</span>
                        <span class="col-span-3">Remarks</span>
                    </div>
                    <div v-for="(item, i) in form.items" :key="i" class="grid grid-cols-1 items-center gap-2 rounded-lg border border-sidebar-border/60 p-2 sm:grid-cols-12">
                        <div class="sm:col-span-4">
                            <p class="text-sm font-medium">{{ item.label }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.section }}</p>
                        </div>
                        <div class="flex gap-1 sm:col-span-3">
                            <button
                                v-for="r in resultOptions"
                                :key="r.value"
                                type="button"
                                class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                                :class="item.result === r.value ? resultStyle[r.value] : 'bg-muted/40 text-muted-foreground hover:bg-muted'"
                                @click="item.result = r.value"
                            >{{ r.label }}</button>
                        </div>
                        <div class="sm:col-span-2">
                            <select v-model.number="item.rating" class="h-8 w-full rounded-md border border-input bg-transparent px-2 text-xs shadow-sm">
                                <option :value="null">—</option>
                                <option v-for="n in 5" :key="n" :value="n">{{ n }}★</option>
                            </select>
                        </div>
                        <div class="sm:col-span-3">
                            <Input v-model="item.remarks" placeholder="Optional" class="h-8 text-xs" />
                        </div>
                    </div>
                </CardContent>
            </Card>

            <!-- Price + overall -->
            <Card>
                <CardHeader><CardTitle class="text-base">Expected Amount &amp; Overall</CardTitle></CardHeader>
                <CardContent class="grid gap-4 sm:grid-cols-3">
                    <div class="grid gap-1.5">
                        <Label>Expected Amount (₹) *</Label>
                        <Input v-model.number="form.expected_amount" type="number" min="0" />
                        <InputError :message="form.errors.expected_amount" />
                    </div>
                    <div class="grid gap-1.5">
                        <Label>Overall Rating <span class="text-xs font-normal text-muted-foreground">(auto)</span></Label>
                        <div class="flex h-9 items-center rounded-md border border-input bg-muted/40 px-3 text-sm text-muted-foreground">
                            <span v-if="overallRating">{{ overallRating }}★</span>
                            <span v-else>Rate items to calculate</span>
                        </div>
                        <p class="text-xs text-muted-foreground">Averaged from your condition-report ratings.</p>
                    </div>
                    <div class="grid gap-1.5 sm:col-span-3">
                        <Label>Overall Remark</Label>
                        <Input v-model="form.overall_remark" placeholder="Anything the buyer should know" />
                    </div>
                </CardContent>
            </Card>

            <div class="flex justify-end">
                <Button :disabled="form.processing" @click="save">{{ isEdit ? 'Save Changes' : 'Save Draft' }}</Button>
            </div>

            <!-- Images + submit (edit only) -->
            <template v-if="isEdit">
                <Card :class="gallery.length === 0 ? 'border-brand-orange/40' : ''">
                    <CardHeader><CardTitle class="text-base">Vehicle Photos <span class="text-brand-red">*</span></CardTitle></CardHeader>
                    <CardContent>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm text-muted-foreground">At least one vehicle photo is required.</p>
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-input px-3 py-1.5 text-sm hover:bg-muted">
                                <Upload class="size-4" /> {{ uploading === 'gallery' ? 'Uploading…' : 'Add image' }}
                                <input type="file" accept="image/*" class="hidden" @change="pickAndUpload('gallery', $event)" />
                            </label>
                        </div>
                        <div v-if="gallery.length" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div v-for="m in gallery" :key="m.id" class="group relative overflow-hidden rounded-lg border">
                                <img :src="m.url" alt="" class="aspect-video w-full object-cover" />
                                <button class="absolute right-1 top-1 rounded-md bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100" @click="removeImage(m.id)"><Trash2 class="size-3.5" /></button>
                            </div>
                        </div>
                        <p v-else class="py-4 text-center text-sm text-muted-foreground">No gallery images yet.</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader><CardTitle class="text-base">Damaged Parts</CardTitle></CardHeader>
                    <CardContent>
                        <div class="mb-2 flex items-center justify-between">
                            <p class="text-sm text-muted-foreground">Close-ups of any dents, scratches or damage.</p>
                            <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-input px-3 py-1.5 text-sm hover:bg-muted">
                                <Upload class="size-4" /> {{ uploading === 'damage' ? 'Uploading…' : 'Add image' }}
                                <input type="file" accept="image/*" class="hidden" @change="pickAndUpload('damage', $event)" />
                            </label>
                        </div>
                        <div v-if="damage.length" class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            <div v-for="m in damage" :key="m.id" class="group relative overflow-hidden rounded-lg border">
                                <img :src="m.url" alt="" class="aspect-video w-full object-cover" />
                                <button class="absolute right-1 top-1 rounded-md bg-black/60 p-1 text-white opacity-0 transition group-hover:opacity-100" @click="removeImage(m.id)"><Trash2 class="size-3.5" /></button>
                            </div>
                        </div>
                        <p v-else class="py-4 text-center text-sm text-muted-foreground">No damage images.</p>
                    </CardContent>
                </Card>

                <Card class="border-brand-red/30">
                    <CardContent class="flex flex-wrap items-center justify-between gap-3 p-4">
                        <div>
                            <p class="font-medium">Ready to submit?</p>
                            <p class="text-sm text-muted-foreground">Once submitted, our team will review it. You can't edit while it's under review.</p>
                            <p v-if="submitBlockedReason" class="mt-1 text-sm font-medium text-brand-orange">{{ submitBlockedReason }}</p>
                        </div>
                        <Button :disabled="!!submitBlockedReason" @click="submitForReview">Submit for Review</Button>
                    </CardContent>
                </Card>
            </template>
        </div>
    </VendorLayout>
</template>
