<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import type { VehicleDetailData } from '@/types/public';
import { Link } from '@inertiajs/vue3';
import { Car } from 'lucide-vue-next';

defineProps<{ vehicles: VehicleDetailData[] }>();

function money(v: string | null): string {
    if (!v) return '—';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const rows: { label: string; get: (v: VehicleDetailData) => string | number }[] = [
    { label: 'Price', get: (v) => money(v.asking_price) },
    { label: 'Year', get: (v) => v.manufacturing_year ?? '—' },
    { label: 'Odometer', get: (v) => (v.odometer_km ? Number(v.odometer_km).toLocaleString('en-IN') + ' km' : '—') },
    { label: 'Fuel', get: (v) => v.fuel_type ?? '—' },
    { label: 'Transmission', get: (v) => v.transmission ?? '—' },
    { label: 'Ownership', get: (v) => (v.ownership_serial ? v.ownership_serial + ' owner' : '—') },
    { label: 'Colour', get: (v) => v.color ?? '—' },
    { label: 'Insurance', get: (v) => v.insurance_status ?? '—' },
    { label: 'Branch', get: (v) => v.branch?.name ?? '—' },
];
</script>

<template>
    <SeoHead title="Compare Cars — Car4Sales" description="Compare pre-owned cars side by side." />

    <PublicLayout>
        <div class="mx-auto max-w-6xl px-4 py-8">
            <h1 class="text-2xl font-bold text-neutral-900">Compare Cars</h1>

            <div v-if="vehicles.length" class="mt-6 overflow-x-auto rounded-xl border bg-white">
                <table class="w-full min-w-[600px] text-sm">
                    <thead>
                        <tr class="border-b">
                            <th class="p-4 text-left text-xs font-medium text-neutral-500">Specification</th>
                            <th v-for="v in vehicles" :key="v.id" class="p-4 text-left">
                                <Link :href="`/cars/${v.slug}`" class="block">
                                    <div class="mb-2 aspect-video overflow-hidden rounded-lg bg-neutral-100">
                                        <img v-if="v.thumbnail" :src="v.thumbnail" :alt="v.title" class="size-full object-cover" />
                                        <div v-else class="flex size-full items-center justify-center text-neutral-300"><Car class="size-10" /></div>
                                    </div>
                                    <span class="font-semibold text-neutral-900 hover:text-brand-maroon">{{ v.title }}</span>
                                </Link>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="row in rows" :key="row.label" class="border-b last:border-0">
                            <td class="p-4 font-medium text-neutral-500">{{ row.label }}</td>
                            <td v-for="v in vehicles" :key="v.id" class="p-4 text-neutral-900">{{ row.get(v) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-else class="mt-6 rounded-xl border bg-white py-16 text-center text-neutral-500">
                No cars selected to compare. <Link href="/cars" class="text-brand-maroon underline">Browse cars</Link> and add them to compare.
            </div>
        </div>
    </PublicLayout>
</template>
