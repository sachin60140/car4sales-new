<script setup lang="ts">
import SeoHead from '@/components/public/SeoHead.vue';
import PublicLayout from '@/layouts/public/PublicLayout.vue';

defineProps<{
    page: { slug: string; title: string; body: string | null; meta_description?: string | null };
}>();
</script>

<template>
    <SeoHead :title="`${page.title} — Car4Sales`" :description="page.meta_description ?? page.title" />

    <PublicLayout>
        <div class="mx-auto max-w-3xl px-4 py-12">
            <h1 class="text-3xl font-extrabold text-neutral-900">{{ page.title }}</h1>
            <div class="prose mt-6 max-w-none text-neutral-700" v-html="page.body ?? '<p>Content coming soon.</p>'" />
        </div>
    </PublicLayout>
</template>
