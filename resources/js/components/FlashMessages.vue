<script setup lang="ts">
import type { SharedData } from '@/types';
import { usePage } from '@inertiajs/vue3';
import { CheckCircle2, XCircle } from 'lucide-vue-next';
import { ref, watch } from 'vue';

const page = usePage<SharedData>();
const visible = ref(false);
const message = ref('');
const kind = ref<'success' | 'error'>('success');
let timer: ReturnType<typeof setTimeout> | null = null;

watch(
    () => [page.props.flash?.success, page.props.flash?.error],
    ([success, error]) => {
        if (!success && !error) {
            return;
        }

        message.value = (success ?? error) as string;
        kind.value = success ? 'success' : 'error';
        visible.value = true;

        if (timer) {
            clearTimeout(timer);
        }

        timer = setTimeout(() => (visible.value = false), 4000);
    },
    { immediate: true },
);
</script>

<template>
    <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="translate-y-2 opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
    >
        <div
            v-if="visible"
            class="fixed right-4 top-4 z-50 flex items-center gap-2 rounded-lg border bg-background px-4 py-3 shadow-lg"
            :class="kind === 'success' ? 'border-green-300 text-green-700 dark:text-green-400' : 'border-red-300 text-red-700 dark:text-red-400'"
            role="status"
        >
            <CheckCircle2 v-if="kind === 'success'" class="size-5" />
            <XCircle v-else class="size-5" />
            <span class="text-sm font-medium">{{ message }}</span>
        </div>
    </Transition>
</template>
