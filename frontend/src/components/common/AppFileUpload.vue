<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue: File | null
    label?: string
    accept?: string
    errorMessages?: string | string[]
    hint?: string
  }>(),
  {
    label: undefined,
    accept: undefined,
    errorMessages: () => [],
    hint: undefined,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: File | null]
}>()
</script>

<template>
  <v-file-input
    :model-value="modelValue"
    :label="label ?? $t('common.create')"
    :accept="accept"
    :error-messages="errorMessages"
    :hint="hint"
    persistent-hint
    prepend-icon=""
    prepend-inner-icon="mdi-paperclip"
    show-size
    @update:model-value="(value) => emit('update:modelValue', Array.isArray(value) ? (value[0] ?? null) : value)"
  />
</template>
