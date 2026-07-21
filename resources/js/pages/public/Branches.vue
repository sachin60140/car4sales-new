<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import { Link } from '@inertiajs/vue3';
import { Mail, MapPin, Phone } from 'lucide-vue-next';

defineProps<{
    branches: {
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
    }[];
}>();
</script>

<template>
    <SeoHead
        title="Our Branches — Car4Sales"
        description="Visit a Car4Sales branch near you. Find addresses, phone numbers and directions to all our showrooms."
    />

    <PublicLayout>
        <section class="bg-brand-maroon py-12 text-white">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h1 class="text-3xl font-extrabold md:text-4xl">Our Branches</h1>
                <p class="mt-2 text-white/80">Visit us at any of our showrooms for a test drive.</p>
            </div>
        </section>

        <div class="mx-auto max-w-6xl px-4 py-10">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="b in branches" :key="b.id" class="flex flex-col rounded-xl border bg-white p-5 shadow-sm">
                    <h2 class="text-lg font-bold text-neutral-900">{{ b.name }}</h2>
                    <p class="mt-2 flex items-start gap-2 text-sm text-neutral-600">
                        <MapPin class="mt-0.5 size-4 shrink-0 text-brand-orange" /> {{ b.address ?? [b.city, b.state].filter(Boolean).join(', ') }}
                    </p>
                    <p v-if="b.phone" class="mt-1 flex items-center gap-2 text-sm text-neutral-600">
                        <Phone class="size-4 text-brand-orange" /> {{ b.phone }}
                    </p>
                    <p v-if="b.email" class="mt-1 flex items-center gap-2 text-sm text-neutral-600">
                        <Mail class="size-4 text-brand-orange" /> {{ b.email }}
                    </p>
                    <div class="mt-4 flex gap-2">
                        <Link :href="`/branches/${b.slug}`" class="rounded-lg bg-brand-yellow px-3 py-1.5 text-xs font-semibold text-brand-maroon"
                            >View Cars</Link
                        >
                        <a
                            v-if="b.latitude && b.longitude"
                            :href="`https://maps.google.com/?q=${b.latitude},${b.longitude}`"
                            target="_blank"
                            rel="noopener"
                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold text-neutral-700"
                            >Directions</a
                        >
                    </div>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
