<script setup lang="ts">
withDefaults(
  defineProps<{
    modelValue: boolean
    title?: string
    text?: string
    loading?: boolean
  }>(),
  {
    loading: false,
  },
)

const emit = defineEmits<{
  'update:modelValue': [value: boolean]
  confirm: []
}>()
</script>

<template>
  <v-dialog
    :model-value="modelValue"
    max-width="440"
    @update:model-value="(value) => emit('update:modelValue', value)"
  >
    <v-card>
      <v-card-title class="text-h6">{{ title ?? $t('common.confirmDeleteTitle') }}</v-card-title>
      <v-card-text>{{ text ?? $t('common.confirmDeleteText') }}</v-card-text>
      <v-card-actions>
        <v-spacer />
        <v-btn variant="text" @click="emit('update:modelValue', false)">{{ $t('common.cancel') }}</v-btn>
        <v-btn color="error" variant="flat" :loading="loading" @click="emit('confirm')">
          {{ $t('common.delete') }}
        </v-btn>
      </v-card-actions>
    </v-card>
  </v-dialog>
</template>
