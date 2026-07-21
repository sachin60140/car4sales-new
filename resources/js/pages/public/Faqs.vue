<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';
import { computed } from 'vue';

const props = defineProps<{
    faqs: { id: number; category: string; question: string; answer: string }[];
}>();

const grouped = computed(() => {
    const map: Record<string, typeof props.faqs> = {};
    props.faqs.forEach((f) => {
        (map[f.category] ??= []).push(f);
    });
    return map;
});

const jsonLd = computed(() => ({
    '@context': 'https://schema.org',
    '@type': 'FAQPage',
    mainEntity: props.faqs.map((f) => ({
        '@type': 'Question',
        name: f.question,
        acceptedAnswer: { '@type': 'Answer', text: f.answer },
    })),
}));
</script>

<template>
    <SeoHead
        title="FAQs — Car4Sales"
        description="Frequently asked questions about buying, selling and financing pre-owned cars with Car4Sales."
        :json-ld="jsonLd"
    />

    <PublicLayout>
        <section class="bg-brand-maroon py-12 text-white">
            <div class="mx-auto max-w-4xl px-4 text-center">
                <h1 class="text-3xl font-extrabold md:text-4xl">Frequently Asked Questions</h1>
                <p class="mt-2 text-white/80">Everything you need to know.</p>
            </div>
        </section>

        <div class="mx-auto max-w-3xl px-4 py-10">
            <div v-for="(items, category) in grouped" :key="category" class="mb-8">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wide text-brand-orange">{{ category }}</h2>
                <div class="divide-y rounded-xl border bg-white">
                    <details v-for="f in items" :key="f.id" class="group px-5 py-4">
                        <summary class="flex cursor-pointer items-center justify-between font-medium text-neutral-900">
                            {{ f.question }}
                            <span class="text-brand-orange transition-transform group-open:rotate-45">+</span>
                        </summary>
                        <p class="mt-2 text-sm text-neutral-600">{{ f.answer }}</p>
                    </details>
                </div>
            </div>
            <p v-if="faqs.length === 0" class="rounded-xl border bg-white py-12 text-center text-neutral-500">No FAQs available.</p>
        </div>
    </PublicLayout>
</template>
