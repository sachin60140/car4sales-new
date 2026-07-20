<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import { BadgeCheck, HandCoins, ShieldCheck, Wrench } from 'lucide-vue-next';

defineProps<{
    page: { title: string; body: string | null; meta_description: string | null } | null;
    stats: { in_stock: number; branches: number };
}>();

const values = [
    { icon: BadgeCheck, title: 'Quality First', text: 'Rigorous multi-point inspection on every car.' },
    { icon: ShieldCheck, title: 'Transparency', text: 'Honest pricing with full history disclosure.' },
    { icon: HandCoins, title: 'Fair Deals', text: 'Best value whether you are buying or selling.' },
    { icon: Wrench, title: 'Reliability', text: 'Refurbished and serviced, ready to drive.' },
];
</script>

<template>
    <SeoHead title="About Us — Car4Sales" description="Car4Sales is a trusted multi-branch pre-owned car dealership offering quality-checked cars, transparent pricing and easy finance." />

    <PublicLayout>
        <section class="bg-gradient-to-br from-brand-maroon to-[hsl(0_62%_15%)] py-14 text-white">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h1 class="text-3xl font-extrabold md:text-4xl">About Car4Sales</h1>
                <p class="mx-auto mt-3 max-w-2xl text-white/80">Your trusted partner for buying and selling quality pre-owned cars. We make car ownership simple, transparent and reliable.</p>
            </div>
        </section>

        <div class="mx-auto max-w-4xl px-4 py-10">
            <div class="grid grid-cols-2 gap-4 text-center sm:grid-cols-4">
                <div class="rounded-xl border bg-white p-5"><p class="text-3xl font-extrabold text-brand-maroon">{{ stats.in_stock }}+</p><p class="text-sm text-neutral-500">Cars in Stock</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-3xl font-extrabold text-brand-maroon">{{ stats.branches }}</p><p class="text-sm text-neutral-500">Branches</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-3xl font-extrabold text-brand-maroon">200+</p><p class="text-sm text-neutral-500">Point Inspection</p></div>
                <div class="rounded-xl border bg-white p-5"><p class="text-3xl font-extrabold text-brand-maroon">100%</p><p class="text-sm text-neutral-500">Verified Papers</p></div>
            </div>

            <div v-if="page?.body" class="prose mt-10 max-w-none text-neutral-700" v-html="page.body" />
            <div v-else class="mt-10 space-y-4 text-neutral-700">
                <p>Car4Sales is a multi-branch pre-owned car dealership built on trust and quality. Every car we sell passes a comprehensive inspection, comes with verified documentation, and is backed by our commitment to transparent pricing.</p>
                <p>Whether you are looking to buy your first car, upgrade to a bigger one, or sell your current vehicle at the best price, our experienced team is here to guide you at every step.</p>
            </div>

            <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                <div v-for="v in values" :key="v.title" class="rounded-xl border bg-white p-6 text-center">
                    <div class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-brand-yellow/20 text-brand-maroon">
                        <component :is="v.icon" class="size-6" />
                    </div>
                    <h3 class="font-semibold text-neutral-900">{{ v.title }}</h3>
                    <p class="mt-1 text-sm text-neutral-600">{{ v.text }}</p>
                </div>
            </div>
        </div>
    </PublicLayout>
</template>
