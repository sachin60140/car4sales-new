<script setup lang="ts">
import type { HTMLAttributes } from 'vue';

defineOptions({
    inheritAttrs: false,
});

interface Props {
    className?: HTMLAttributes['class'];
}

defineProps<Props>();
</script>

<template>
    <!-- Car4Sales emblem: side-profile car with speed swoosh. Uses currentColor. -->
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 64 64" fill="none" :class="className" v-bind="$attrs">
        <!-- speed swoosh -->
        <path
            d="M4 22c14-9 30-11 45-6"
            stroke="currentColor"
            stroke-width="3.2"
            stroke-linecap="round"
            opacity="0.55"
        />
        <!-- car body -->
        <path
            fill="currentColor"
            d="M6 41c0-3.4 2.6-6.2 5.9-6.5l3.9-8.2c1.2-2.5 3.7-4.1 6.5-4.1h15.1c2.7 0 5.2 1.4 6.6 3.7l4.4 7.2 5 1.1c3 .7 5.1 3.3 5.1 6.4V43c0 1.5-1.2 2.7-2.7 2.7h-3.3a7.3 7.3 0 0 0-14.5 0H25.3a7.3 7.3 0 0 0-14.5 0H8.7C7.2 45.7 6 44.5 6 43v-2Zm18.4-15.3-2.8 6.6h9.2v-6.6h-6.4Zm10.2 0v6.6h9l-3.6-5.9c-.5-.4-1.2-.7-1.9-.7h-3.5Z"
        />
        <!-- wheels -->
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M18 39a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13Zm0 4a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Z"
        />
        <path
            fill="currentColor"
            fill-rule="evenodd"
            clip-rule="evenodd"
            d="M46 39a6.5 6.5 0 1 0 0 13 6.5 6.5 0 0 0 0-13Zm0 4a2.5 2.5 0 1 1 0 5 2.5 2.5 0 0 1 0-5Z"
        />
    </svg>
</template>
