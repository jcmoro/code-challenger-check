<script setup lang="ts">
import type { SortDirection } from '@/composables/useSort';
import { es } from '@/i18n/es';

interface Props {
  direction: SortDirection;
}
const props = defineProps<Props>();
defineEmits<{
  toggle: [];
}>();
</script>

<template>
  <button
    type="button"
    class="sort-toggle"
    :aria-label="props.direction === 'asc' ? es.results.sortAsc : es.results.sortDesc"
    :aria-pressed="props.direction === 'desc' ? 'true' : 'false'"
    @click="$emit('toggle')"
  >
    <span aria-hidden="true">{{ props.direction === 'asc' ? '↑' : '↓' }}</span>
  </button>
</template>
