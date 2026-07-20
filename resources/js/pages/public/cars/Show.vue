<script setup lang="ts">
import EnquiryForm from '@/components/public/EnquiryForm.vue';
import SeoHead from '@/components/public/SeoHead.vue';
import VehicleCard from '@/components/public/VehicleCard.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import type { FinanceEstimate, VehicleCardData, VehicleDetailData } from '@/types/public';
import { Link } from '@inertiajs/vue3';
import { Calendar, Car, CheckCircle2, Fuel, Gauge, MapPin, Palette, Settings2, Shield, Users } from 'lucide-vue-next';
import { computed, ref } from 'vue';

const props = defineProps<{
    vehicle: VehicleDetailData;
    financeEstimate: FinanceEstimate | null;
    similar: VehicleCardData[];
}>();

const activeImage = ref(0);

function money(v: string | number | null): string {
    if (v === null || v === undefined || v === '') return 'Price on request';
    return '₹' + Number(v).toLocaleString('en-IN');
}

const specs = computed(() => [
    { icon: Calendar, label: 'Mfg. Year', value: props.vehicle.manufacturing_year ?? '—' },
    { icon: Gauge, label: 'Odometer', value: props.vehicle.odometer_km ? Number(props.vehicle.odometer_km).toLocaleString('en-IN') + ' km' : '—' },
    { icon: Fuel, label: 'Fuel', value: props.vehicle.fuel_type ?? '—' },
    { icon: Settings2, label: 'Transmission', value: props.vehicle.transmission ?? '—' },
    { icon: Users, label: 'Ownership', value: props.vehicle.ownership_serial ? props.vehicle.ownership_serial + ' owner' : '—' },
    { icon: Palette, label: 'Colour', value: props.vehicle.color ?? '—' },
    { icon: Shield, label: 'Insurance', value: props.vehicle.insurance_status ?? '—' },
    { icon: Car, label: 'Reg. State', value: props.vehicle.registration_state ?? '—' },
]);

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'Car',
    name: props.vehicle.title,
    brand: props.vehicle.make,
    model: props.vehicle.model,
    vehicleModelDate: props.vehicle.manufacturing_year,
    fuelType: props.vehicle.fuel_type,
    mileageFromOdometer: props.vehicle.odometer_km ? { '@type': 'QuantitativeValue', value: props.vehicle.odometer_km, unitCode: 'KMT' } : undefined,
    offers: props.vehicle.asking_price
        ? { '@type': 'Offer', price: props.vehicle.asking_price, priceCurrency: 'INR', availability: props.vehicle.availability === 'available' ? 'https://schema.org/InStock' : 'https://schema.org/LimitedAvailability' }
        : undefined,
}));
</script>

<template>
    <SeoHead
        :title="`${vehicle.title} — Car4Sales`"
        :description="`${vehicle.title} for sale at ${money(vehicle.asking_price)}. ${vehicle.manufacturing_year} · ${vehicle.fuel_type ?? ''} · ${vehicle.odometer_km ? Number(vehicle.odometer_km).toLocaleString('en-IN') + ' km' : ''}. Quality-checked pre-owned car.`"
        :image="vehicle.gallery[0]?.url"
        :json-ld="jsonLd"
    />

    <PublicLayout>
        <div class="mx-auto max-w-7xl px-4 py-6">
            <!-- Breadcrumb -->
            <nav class="mb-4 flex items-center gap-1 text-xs text-neutral-500">
                <Link href="/" class="hover:text-brand-maroon">Home</Link><span>/</span>
                <Link href="/cars" class="hover:text-brand-maroon">Cars</Link><span>/</span>
                <span class="text-neutral-800">{{ vehicle.title }}</span>
            </nav>

            <div class="grid gap-6 lg:grid-cols-3">
                <!-- Gallery + details -->
                <div class="lg:col-span-2">
                    <div class="overflow-hidden rounded-xl border bg-white">
                        <div class="aspect-[16/10] bg-neutral-100">
                            <img v-if="vehicle.gallery.length" :src="vehicle.gallery[activeImage]?.url" :alt="vehicle.title" class="size-full object-cover" />
                            <div v-else class="flex size-full flex-col items-center justify-center text-neutral-300">
                                <Car class="size-24" /><span class="mt-2 text-sm">No photos available</span>
                            </div>
                        </div>
                        <div v-if="vehicle.gallery.length > 1" class="flex gap-2 overflow-x-auto p-2">
                            <button
                                v-for="(g, i) in vehicle.gallery"
                                :key="i"
                                class="size-16 shrink-0 overflow-hidden rounded-lg border-2"
                                :class="i === activeImage ? 'border-brand-yellow' : 'border-transparent'"
                                @click="activeImage = i"
                            >
                                <img :src="g.url" :alt="`${vehicle.title} ${i + 1}`" class="size-full object-cover" />
                            </button>
                        </div>
                    </div>

                    <!-- Specs -->
                    <div class="mt-6 rounded-xl border bg-white p-5">
                        <h2 class="mb-4 text-lg font-bold text-neutral-900">Specifications</h2>
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                            <div v-for="s in specs" :key="s.label" class="text-sm">
                                <component :is="s.icon" class="mb-1 size-4 text-brand-orange" />
                                <p class="text-neutral-500">{{ s.label }}</p>
                                <p class="font-semibold text-neutral-900">{{ s.value }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Description + features -->
                    <div v-if="vehicle.description || vehicle.key_features.length" class="mt-6 rounded-xl border bg-white p-5">
                        <h2 class="mb-3 text-lg font-bold text-neutral-900">Overview</h2>
                        <p v-if="vehicle.description" class="text-sm text-neutral-700">{{ vehicle.description }}</p>
                        <ul v-if="vehicle.key_features.length" class="mt-3 grid grid-cols-2 gap-2 text-sm sm:grid-cols-3">
                            <li v-for="f in vehicle.key_features" :key="f" class="flex items-center gap-1.5 text-neutral-700">
                                <CheckCircle2 class="size-4 text-green-600" /> {{ f }}
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Sidebar: price + enquiry -->
                <div class="space-y-4">
                    <div class="rounded-xl border bg-white p-5 shadow-sm">
                        <h1 class="text-xl font-bold text-neutral-900">{{ vehicle.title }}</h1>
                        <p class="mt-1 flex items-center gap-1 text-sm text-neutral-500">
                            <MapPin class="size-3.5" /> {{ vehicle.branch?.name }}<span v-if="vehicle.branch?.city">, {{ vehicle.branch.city }}</span>
                        </p>
                        <p class="mt-3 text-3xl font-extrabold text-brand-maroon">{{ money(vehicle.asking_price) }}</p>
                        <span
                            class="mt-2 inline-flex rounded-full px-2 py-0.5 text-xs font-medium"
                            :class="vehicle.availability === 'available' ? 'bg-green-100 text-green-700' : 'bg-brand-red/15 text-brand-red'"
                        >{{ vehicle.availability === 'available' ? 'Available' : 'Reserved' }}</span>

                        <div v-if="financeEstimate" class="mt-4 rounded-lg bg-brand-yellow/10 p-3 text-sm">
                            <p class="text-neutral-600">EMI starts at</p>
                            <p class="text-lg font-bold text-brand-maroon">{{ money(financeEstimate.emi) }}/mo</p>
                            <p class="text-xs text-neutral-500">{{ financeEstimate.tenure_months }} months @ {{ financeEstimate.interest_rate }}% · <Link href="/finance" class="underline">Calculate</Link></p>
                        </div>
                    </div>

                    <div class="rounded-xl border bg-white p-5 shadow-sm">
                        <EnquiryForm type="vehicle" :vehicle-id="vehicle.id" heading="Interested? Enquire now" submit-label="Send Enquiry" purpose="enquiry" />
                    </div>
                    <div class="rounded-xl border bg-white p-5 shadow-sm">
                        <EnquiryForm type="test_drive" :vehicle-id="vehicle.id" heading="Book a Test Drive" submit-label="Request Test Drive" purpose="enquiry" />
                    </div>
                </div>
            </div>

            <!-- Similar -->
            <section v-if="similar.length" class="mt-12">
                <h2 class="mb-4 text-xl font-bold text-neutral-900">Similar Cars</h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <VehicleCard v-for="v in similar" :key="v.id" :vehicle="v" />
                </div>
            </section>
        </div>
    </PublicLayout>
</template>
