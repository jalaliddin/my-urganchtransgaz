<script setup lang="ts">
import { ref } from 'vue'
import type { VDataTableServer } from 'vuetify/components'

type Headers = InstanceType<typeof VDataTableServer>['$props']['headers']
type SortItem = { key: string; order?: 'asc' | 'desc' }

const props = withDefaults(
  defineProps<{
    headers: Headers
    items: Record<string, unknown>[]
    itemsLength: number
    loading?: boolean
    page: number
    itemsPerPage: number
    search?: string
  }>(),
  {
    loading: false,
    search: '',
  },
)

const emit = defineEmits<{
  'update:page': [value: number]
  'update:itemsPerPage': [value: number]
  'update:search': [value: string]
  'update:sortBy': [value: SortItem[]]
}>()

const searchModel = ref(props.search)
let searchDebounce: ReturnType<typeof setTimeout> | undefined

function onSearchInput(value: string) {
  searchModel.value = value
  clearTimeout(searchDebounce)
  searchDebounce = setTimeout(() => emit('update:search', value), 350)
}
</script>

<template>
  <v-card>
    <v-card-text class="pb-0">
      <v-text-field
        :model-value="searchModel"
        :placeholder="$t('common.search')"
        prepend-inner-icon="mdi-magnify"
        density="comfortable"
        variant="outlined"
        hide-details
        clearable
        class="mb-2"
        style="max-width: 360px"
        @update:model-value="(value) => onSearchInput(value ?? '')"
      />
    </v-card-text>

    <v-data-table-server
      :headers="headers"
      :items="items"
      :items-length="itemsLength"
      :loading="loading"
      :page="page"
      :items-per-page="itemsPerPage"
      item-value="id"
      @update:page="(value) => emit('update:page', value)"
      @update:items-per-page="(value) => emit('update:itemsPerPage', value)"
      @update:sort-by="(value) => emit('update:sortBy', value)"
    >
      <template v-for="(_, slotName) in $slots" #[slotName]="slotProps">
        <slot :name="slotName" v-bind="slotProps ?? {}" />
      </template>

      <template #no-data>
        <slot name="empty">
          <div class="py-8">{{ $t('common.noData') }}</div>
        </slot>
      </template>
    </v-data-table-server>
  </v-card>
</template>
