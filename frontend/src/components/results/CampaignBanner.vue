<script setup lang="ts">
import { es } from '@/i18n/es';

interface Props {
  percentage: number;
}
defineProps<Props>();
</script>

<template>
  <aside class="campaign-banner" role="status">
    <strong>{{ es.results.campaignBanner.replace('5%', `${percentage}%`) }}</strong>
  </aside>
</template>
