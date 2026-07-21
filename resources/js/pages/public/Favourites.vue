<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import VehicleCard from '@/components/public/VehicleCard.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import type { VehicleCardData } from '@/types/public';
import { Link } from '@inertiajs/vue3';
import { Heart } from 'lucide-vue-next';

defineProps<{ vehicles: VehicleCardData[] }>();
</script>

<template>
    <SeoHead title="My Favourites — Car4Sales" description="Your shortlisted cars." />

    <PublicLayout>
        <div class="mx-auto max-w-7xl px-4 py-8">
            <h1 class="text-2xl font-bold text-neutral-900">My Favourites</h1>
            <p class="text-sm text-neutral-500">{{ vehicles.length }} saved cars</p>

            <div v-if="vehicles.length" class="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <VehicleCard v-for="v in vehicles" :key="v.id" :vehicle="v" :favourite="true" />
            </div>
            <div v-else class="mt-6 rounded-xl border bg-white py-16 text-center">
                <Heart class="mx-auto size-12 text-neutral-300" />
                <p class="mt-3 text-neutral-500">You haven't saved any cars yet.</p>
                <Link href="/cars" class="mt-4 inline-block rounded-lg bg-brand-maroon px-5 py-2.5 text-sm font-semibold text-white"
                    >Browse Cars</Link
                >
            </div>
        </div>
    </PublicLayout>
</template>
