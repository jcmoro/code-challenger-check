<script setup lang="ts">
import { es } from '@/i18n/es';

interface Props {
  percentage: number;
}
defineProps<Props>();
</script>

<template>
  <output class="campaign-banner">
    <strong>{{ es.results.campaignBanner.replace('5%', `${percentage}%`) }}</strong>
  </output>
</template>
