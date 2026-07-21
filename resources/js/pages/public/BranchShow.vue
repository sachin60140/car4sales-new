<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import VehicleCard from '@/components/public/VehicleCard.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import type { VehicleCardData } from '@/types/public';
import { Link } from '@inertiajs/vue3';
import { Mail, MapPin, Phone } from 'lucide-vue-next';

const props = defineProps<{
    branch: {
        id: number;
        name: string;
        slug: string;
        city: string | null;
        state: string | null;
        address: string | null;
        phone: string | null;
        email: string | null;
        latitude: string | null;
        longitude: string | null;
    };
    vehicles: VehicleCardData[];
}>();

const jsonLd = {
    '@context': 'https://schema.org',
    '@type': 'AutoDealer',
    name: `Car4Sales — ${props.branch.name}`,
    address: props.branch.address,
    telephone: props.branch.phone,
};
</script>

<template>
    <SeoHead
        :title="`${branch.name} — Car4Sales`"
        :description="`Cars available at Car4Sales ${branch.name}, ${branch.city ?? ''}. Visit for a test drive.`"
        :json-ld="jsonLd"
    />

    <PublicLayout>
        <div class="mx-auto max-w-7xl px-4 py-8">
            <nav class="mb-4 flex items-center gap-1 text-xs text-neutral-500">
                <Link href="/branches" class="hover:text-brand-maroon">Branches</Link><span>/</span
                ><span class="text-neutral-800">{{ branch.name }}</span>
            </nav>

            <div class="rounded-2xl border bg-white p-6 shadow-sm">
                <h1 class="text-2xl font-bold text-neutral-900">{{ branch.name }}</h1>
                <div class="mt-2 flex flex-wrap gap-4 text-sm text-neutral-600">
                    <span class="flex items-center gap-1"
                        ><MapPin class="size-4 text-brand-orange" />
                        {{ branch.address ?? [branch.city, branch.state].filter(Boolean).join(', ') }}</span
                    >
                    <span v-if="branch.phone" class="flex items-center gap-1"><Phone class="size-4 text-brand-orange" /> {{ branch.phone }}</span>
                    <span v-if="branch.email" class="flex items-center gap-1"><Mail class="size-4 text-brand-orange" /> {{ branch.email }}</span>
                </div>
            </div>

            <h2 class="mb-4 mt-8 text-xl font-bold text-neutral-900">Available Cars</h2>
            <div v-if="vehicles.length" class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <VehicleCard v-for="v in vehicles" :key="v.id" :vehicle="v" />
            </div>
            <p v-else class="rounded-xl border bg-white py-12 text-center text-neutral-500">No cars currently available at this branch.</p>
        </div>
    </PublicLayout>
</template>
