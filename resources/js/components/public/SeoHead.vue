<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    title: string;
    description?: string;
    canonical?: string;
    image?: string;
    jsonLd?: object | object[];
}>();

const jsonLdString = computed(() => (props.jsonLd ? JSON.stringify(props.jsonLd) : ''));
</script>

<template>
    <Head :title="title">
        <meta v-if="description" head-key="description" name="description" :content="description" />
        <link v-if="canonical" head-key="canonical" rel="canonical" :href="canonical" />
        <meta head-key="og:title" property="og:title" :content="title" />
        <meta v-if="description" head-key="og:description" property="og:description" :content="description" />
        <meta head-key="og:type" property="og:type" content="website" />
        <meta v-if="image" head-key="og:image" property="og:image" :content="image" />
        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="title" />
        <component :is="'script'" v-if="jsonLdString" type="application/ld+json">{{ jsonLdString }}</component>
    </Head>
</template>
