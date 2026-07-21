<script setup lang="ts">
import { cn } from '@/lib/utils'
import { Check } from 'lucide-vue-next'
import { CheckboxIndicator, CheckboxRoot } from 'radix-vue'
import type { HTMLAttributes } from 'vue'

// radix-vue's CheckboxRoot is controlled via `checked` / `update:checked`. Callers
// across the app use the `v-model` / `:model-value` convention, so translate here.
const props = defineProps<{
  modelValue?: boolean | 'indeterminate'
  disabled?: boolean
  class?: HTMLAttributes['class']
}>()

const emits = defineEmits<{
  'update:modelValue': [value: boolean | 'indeterminate']
}>()
</script>

<template>
  <CheckboxRoot
    :checked="modelValue ?? false"
    :disabled="disabled"
    :class="
      cn('peer size-5 shrink-0 rounded-sm border border-input ring-offset-background focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 data-[state=checked]:bg-primary data-[state=checked]:text-primary-foreground data-[state=checked]:border-accent-foreground',
         props.class)"
    @update:checked="emits('update:modelValue', $event)"
  >
    <CheckboxIndicator class="flex h-full w-full items-center justify-center text-current">
      <slot>
        <Check class="size-3.5 stroke-[3]" />
      </slot>
    </CheckboxIndicator>
  </CheckboxRoot>
</template>
