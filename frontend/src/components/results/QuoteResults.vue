<script setup lang="ts">
import CampaignBanner from './CampaignBanner.vue';
import QuoteTable from './QuoteTable.vue';
import EmptyResults from '@/components/feedback/EmptyResults.vue';
import ErrorMessage from '@/components/feedback/ErrorMessage.vue';
import LoadingIndicator from '@/components/feedback/LoadingIndicator.vue';
import type { ApiError } from '@/api/client';
import type { CalculateResponse } from '@/domain/types';

interface Props {
  loading: boolean;
  error: ApiError | null;
  data: CalculateResponse | null;
}
defineProps<Props>();
defineEmits<{
  retry: [];
}>();
</script>

<template>
  <section class="results" aria-live="polite">
    <CampaignBanner v-if="data?.campaign.active" :percentage="data.campaign.percentage" />

    <LoadingIndicator v-if="loading" />
    <ErrorMessage v-else-if="error" :error="error" @retry="$emit('retry')" />

    <template v-else-if="data">
      <QuoteTable v-if="data.quotes.length > 0" :quotes="data.quotes" />
      <EmptyResults v-else />
    </template>
  </section>
</template>
