<script setup lang="ts">
import { computed } from 'vue';
import type { ApiError } from '@/api/client';
import { es } from '@/i18n/es';

interface Props {
  error: ApiError;
}
const props = defineProps<Props>();
defineEmits<{
  retry: [];
}>();

const message = computed(() => {
  switch (props.error.kind) {
    case 'network':
      return es.errors.network;
    case 'server':
      return es.errors.server;
    case 'validation':
      return es.errors.validation;
    default:
      return es.errors.unknown;
  }
});
</script>

<template>
  <div class="error" role="alert">
    <p class="error__message">{{ message }}</p>
    <ul v-if="props.error.violations && props.error.violations.length" class="error__violations">
      <li v-for="v in props.error.violations" :key="`${v.field}-${v.message}`">
        <strong>{{ v.field }}</strong
        >: {{ v.message }}
      </li>
    </ul>
    <button type="button" class="error__retry" @click="$emit('retry')">Reintentar</button>
  </div>
</template>
