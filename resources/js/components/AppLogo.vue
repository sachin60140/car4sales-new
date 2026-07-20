<script setup lang="ts">
import AppLogoIcon from '@/components/AppLogoIcon.vue';
</script>

<template>
    <div
        class="flex aspect-square size-9 items-center justify-center rounded-lg bg-brand-yellow text-brand-maroon shadow-sm ring-1 ring-black/5"
    >
        <AppLogoIcon class="size-6" />
    </div>
    <div class="ml-2 grid flex-1 text-left leading-none">
        <span class="text-base font-extrabold tracking-tight">
            <span class="text-sidebar-foreground">Car</span><span class="text-brand-yellow">4</span><span class="text-sidebar-foreground">Sales</span>
        </span>
        <span class="mt-0.5 text-[10px] font-medium uppercase tracking-wider text-sidebar-foreground/60">Deals Pre-owned Cars</span>
    </div>
</template>
