<script setup lang="ts">
import type { VehicleCardData } from '@/types/public';
import { Link, router } from '@inertiajs/vue3';
import { Car, Fuel, Gauge, Heart, MapPin, Settings2 } from 'lucide-vue-next';

defineProps<{
    vehicle: VehicleCardData;
    favourite?: boolean;
}>();

function money(v: string | null): string {
    if (!v) return 'Price on request';
    return '₹' + Number(v).toLocaleString('en-IN');
}

function toggleFavourite(id: number) {
    router.post(`/favourites/${id}`, {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <div class="group flex flex-col overflow-hidden rounded-xl border bg-white shadow-sm transition-all hover:shadow-md">
        <Link :href="`/cars/${vehicle.slug}`" class="relative block aspect-[4/3] overflow-hidden bg-neutral-100">
            <img
                v-if="vehicle.thumbnail"
                :src="vehicle.thumbnail"
                :alt="vehicle.title"
                loading="lazy"
                class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
            />
            <div v-else class="flex size-full flex-col items-center justify-center text-neutral-300">
                <Car class="size-16" />
                <span class="mt-1 text-xs">No photo</span>
            </div>
            <span
                v-if="vehicle.availability === 'reserved'"
                class="absolute left-2 top-2 rounded-full bg-brand-red px-2 py-0.5 text-xs font-semibold text-white"
            >Reserved</span>
            <span
                v-if="vehicle.is_featured"
                class="absolute right-2 top-2 rounded-full bg-brand-yellow px-2 py-0.5 text-xs font-semibold text-brand-maroon"
            >Featured</span>
            <button
                class="absolute bottom-2 right-2 flex size-8 items-center justify-center rounded-full bg-white/90 shadow"
                :aria-label="favourite ? 'Remove from favourites' : 'Add to favourites'"
                @click.prevent="toggleFavourite(vehicle.id)"
            >
                <Heart class="size-4" :class="favourite ? 'fill-brand-red text-brand-red' : 'text-neutral-500'" />
            </button>
        </Link>

        <div class="flex flex-1 flex-col p-4">
            <Link :href="`/cars/${vehicle.slug}`" class="line-clamp-1 font-semibold text-neutral-900 hover:text-brand-maroon">
                {{ vehicle.title }}
            </Link>
            <p class="mt-0.5 text-sm text-neutral-500">{{ vehicle.manufacturing_year }}</p>

            <div class="mt-3 grid grid-cols-3 gap-2 text-xs text-neutral-600">
                <span class="flex items-center gap-1"><Gauge class="size-3.5" /> {{ vehicle.odometer_km ? Number(vehicle.odometer_km).toLocaleString('en-IN') + ' km' : '—' }}</span>
                <span class="flex items-center gap-1"><Fuel class="size-3.5" /> {{ vehicle.fuel_type ?? '—' }}</span>
                <span class="flex items-center gap-1"><Settings2 class="size-3.5" /> {{ vehicle.transmission ?? '—' }}</span>
            </div>

            <div class="mt-3 flex items-end justify-between border-t pt-3">
                <div>
                    <p class="text-lg font-bold text-brand-maroon">{{ money(vehicle.asking_price) }}</p>
                    <p v-if="vehicle.branch" class="flex items-center gap-1 text-xs text-neutral-500">
                        <MapPin class="size-3" /> {{ vehicle.branch.city ?? vehicle.branch.name }}
                    </p>
                </div>
                <Link :href="`/cars/${vehicle.slug}`" class="rounded-lg bg-brand-yellow px-3 py-1.5 text-xs font-semibold text-brand-maroon hover:bg-brand-yellow/90">
                    View
                </Link>
            </div>
        </div>
    </div>
</template>
