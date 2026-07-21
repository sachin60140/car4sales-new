<script setup lang="ts">
import Pagination from '@/components/Pagination.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import VehicleCard from '@/components/public/VehicleCard.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import type { Paginated } from '@/types';
import type { VehicleCardData } from '@/types/public';
import { router } from '@inertiajs/vue3';
import { SlidersHorizontal, X } from 'lucide-vue-next';
import { reactive, ref, watch } from 'vue';

interface FilterOptions {
    makes: string[];
    fuelTypes: string[];
    transmissions: string[];
    bodyTypes: string[];
    colors: string[];
    branches: { id: number; name: string }[];
    yearRange: { min: number; max: number };
    priceRange: { min: number; max: number };
}

const props = defineProps<{
    vehicles: Paginated<VehicleCardData>;
    filterOptions: FilterOptions;
    filters: Record<string, string | number | undefined>;
}>();

const filters = reactive({
    search: props.filters.search ?? '',
    make: props.filters.make ?? '',
    fuel_type: props.filters.fuel_type ?? '',
    transmission: props.filters.transmission ?? '',
    body_type: props.filters.body_type ?? '',
    color: props.filters.color ?? '',
    ownership: props.filters.ownership ?? '',
    branch_id: props.filters.branch_id ?? '',
    price_min: props.filters.price_min ?? '',
    price_max: props.filters.price_max ?? '',
    year_min: props.filters.year_min ?? '',
    year_max: props.filters.year_max ?? '',
    km_max: props.filters.km_max ?? '',
    availability: props.filters.availability ?? '',
    sort: props.filters.sort ?? '',
});

const view = ref<'grid' | 'list'>((props.filters.view as 'grid' | 'list') ?? 'grid');
const showFilters = ref(false);
const canonical = typeof window !== 'undefined' ? window.location.origin + '/cars' : undefined;

let timer: ReturnType<typeof setTimeout> | null = null;
watch(filters, () => {
    if (timer) clearTimeout(timer);
    timer = setTimeout(apply, 400);
});
watch(() => filters.sort, apply);

function apply() {
    const q: Record<string, string> = {};
    Object.entries(filters).forEach(([k, v]) => {
        if (v !== '' && v !== null && v !== undefined) q[k] = String(v);
    });
    router.get('/cars', q, { preserveState: true, replace: true, preserveScroll: true });
}

function reset() {
    Object.keys(filters).forEach((k) => ((filters as Record<string, string>)[k] = ''));
}
</script>

<template>
    <SeoHead
        title="Used Cars for Sale — Car4Sales"
        description="Browse certified pre-owned cars. Filter by brand, price, year, fuel and more. Quality-checked and ready to drive."
        :canonical="canonical"
    />

    <PublicLayout>
        <div class="mx-auto max-w-7xl px-4 py-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-neutral-900">Available Cars</h1>
                    <p class="text-sm text-neutral-500">{{ vehicles.total }} cars found</p>
                </div>
                <button class="flex items-center gap-1 rounded-lg border px-3 py-2 text-sm lg:hidden" @click="showFilters = true">
                    <SlidersHorizontal class="size-4" /> Filters
                </button>
            </div>

            <div class="mt-4 flex gap-6">
                <!-- Filters sidebar -->
                <aside
                    class="fixed inset-0 z-50 overflow-y-auto bg-white p-5 lg:static lg:z-0 lg:block lg:w-64 lg:shrink-0 lg:overflow-visible lg:bg-transparent lg:p-0"
                    :class="showFilters ? 'block' : 'hidden'"
                >
                    <div class="mb-4 flex items-center justify-between lg:hidden">
                        <span class="font-semibold">Filters</span>
                        <button @click="showFilters = false"><X class="size-5" /></button>
                    </div>

                    <div class="space-y-4 rounded-xl border bg-white p-4 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">Filters</span>
                            <button class="text-xs text-brand-maroon underline" @click="reset">Clear all</button>
                        </div>
                        <input v-model="filters.search" placeholder="Search…" class="w-full rounded-lg border px-3 py-2" />

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-neutral-500">Brand</span>
                            <select v-model="filters.make" class="w-full rounded-lg border px-3 py-2">
                                <option value="">All brands</option>
                                <option v-for="m in filterOptions.makes" :key="m" :value="m">{{ m }}</option>
                            </select>
                        </label>

                        <div class="grid grid-cols-2 gap-2">
                            <label class="block"
                                ><span class="mb-1 block text-xs font-medium text-neutral-500">Fuel</span>
                                <select v-model="filters.fuel_type" class="w-full rounded-lg border px-2 py-2">
                                    <option value="">Any</option>
                                    <option v-for="f in filterOptions.fuelTypes" :key="f" :value="f">{{ f }}</option>
                                </select>
                            </label>
                            <label class="block"
                                ><span class="mb-1 block text-xs font-medium text-neutral-500">Transmission</span>
                                <select v-model="filters.transmission" class="w-full rounded-lg border px-2 py-2">
                                    <option value="">Any</option>
                                    <option v-for="t in filterOptions.transmissions" :key="t" :value="t">{{ t }}</option>
                                </select>
                            </label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-neutral-500">Body type</span>
                            <select v-model="filters.body_type" class="w-full rounded-lg border px-3 py-2">
                                <option value="">Any</option>
                                <option v-for="b in filterOptions.bodyTypes" :key="b" :value="b">{{ b }}</option>
                            </select>
                        </label>

                        <div class="grid grid-cols-2 gap-2">
                            <label class="block"
                                ><span class="mb-1 block text-xs font-medium text-neutral-500">Min price</span>
                                <input v-model="filters.price_min" type="number" placeholder="₹" class="w-full rounded-lg border px-2 py-2"
                            /></label>
                            <label class="block"
                                ><span class="mb-1 block text-xs font-medium text-neutral-500">Max price</span>
                                <input v-model="filters.price_max" type="number" placeholder="₹" class="w-full rounded-lg border px-2 py-2"
                            /></label>
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <label class="block"
                                ><span class="mb-1 block text-xs font-medium text-neutral-500">Year from</span>
                                <input
                                    v-model="filters.year_min"
                                    type="number"
                                    :placeholder="String(filterOptions.yearRange.min)"
                                    class="w-full rounded-lg border px-2 py-2"
                            /></label>
                            <label class="block"
                                ><span class="mb-1 block text-xs font-medium text-neutral-500">Max km</span>
                                <input v-model="filters.km_max" type="number" placeholder="km" class="w-full rounded-lg border px-2 py-2"
                            /></label>
                        </div>

                        <label class="block">
                            <span class="mb-1 block text-xs font-medium text-neutral-500">Branch</span>
                            <select v-model="filters.branch_id" class="w-full rounded-lg border px-3 py-2">
                                <option value="">All branches</option>
                                <option v-for="b in filterOptions.branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                            </select>
                        </label>

                        <label class="flex items-center gap-2">
                            <input v-model="filters.availability" type="checkbox" true-value="available" false-value="" /> Available only
                        </label>

                        <button
                            class="w-full rounded-lg bg-brand-maroon py-2 text-sm font-semibold text-white lg:hidden"
                            @click="showFilters = false"
                        >
                            Show {{ vehicles.total }} cars
                        </button>
                    </div>
                </aside>

                <!-- Results -->
                <div class="flex-1">
                    <div class="mb-4 flex items-center justify-between rounded-lg border bg-white px-3 py-2 text-sm">
                        <div class="flex gap-1">
                            <button
                                class="rounded px-2 py-1"
                                :class="view === 'grid' ? 'bg-brand-yellow/20 font-medium text-brand-maroon' : 'text-neutral-500'"
                                @click="view = 'grid'"
                            >
                                Grid
                            </button>
                            <button
                                class="rounded px-2 py-1"
                                :class="view === 'list' ? 'bg-brand-yellow/20 font-medium text-brand-maroon' : 'text-neutral-500'"
                                @click="view = 'list'"
                            >
                                List
                            </button>
                        </div>
                        <select v-model="filters.sort" class="rounded-lg border px-2 py-1.5">
                            <option value="">Newest first</option>
                            <option value="price_asc">Price: Low to High</option>
                            <option value="price_desc">Price: High to Low</option>
                            <option value="year_desc">Year: Newest</option>
                            <option value="km_asc">Km: Lowest</option>
                        </select>
                    </div>

                    <div v-if="vehicles.data.length === 0" class="rounded-xl border bg-white py-16 text-center text-neutral-500">
                        No cars match your filters. Try widening your search.
                    </div>
                    <div v-else :class="view === 'grid' ? 'grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3' : 'grid grid-cols-1 gap-4'">
                        <VehicleCard v-for="v in vehicles.data" :key="v.id" :vehicle="v" />
                    </div>

                    <Pagination :paginator="vehicles" />
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
