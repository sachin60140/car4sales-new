<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';

defineProps<{
    testimonials: { id: number; customer_name: string; city: string | null; rating: number; message: string }[];
}>();
</script>

<template>
    <SeoHead
        title="Customer Reviews — Car4Sales"
        description="Read what our customers say about their experience buying and selling cars with Car4Sales."
    />

    <PublicLayout>
        <section class="bg-gradient-to-br from-brand-yellow to-brand-gold py-12 text-brand-maroon">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h1 class="text-3xl font-extrabold md:text-4xl">Customer Reviews</h1>
                <p class="mt-2">Real experiences from our happy customers.</p>
            </div>
        </section>

        <div class="mx-auto max-w-6xl px-4 py-10">
            <div v-if="testimonials.length" class="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                <div v-for="t in testimonials" :key="t.id" class="rounded-xl border bg-white p-6 shadow-sm">
                    <div class="flex gap-0.5 text-brand-yellow">
                        <span v-for="n in 5" :key="n">{{ n <= t.rating ? '★' : '☆' }}</span>
                    </div>
                    <p class="mt-3 text-sm text-neutral-700">"{{ t.message }}"</p>
                    <p class="mt-3 text-sm font-semibold text-neutral-900">
                        {{ t.customer_name }}<span v-if="t.city" class="font-normal text-neutral-500">, {{ t.city }}</span>
                    </p>
                </div>
            </div>
            <p v-else class="rounded-xl border bg-white py-12 text-center text-neutral-500">No reviews yet.</p>
        </div>
    </PublicLayout>
</template>
