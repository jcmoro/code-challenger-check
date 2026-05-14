<script setup lang="ts">
import { RouterLink } from 'vue-router';
import QuoteForm from '@/components/form/QuoteForm.vue';
import QuoteResults from '@/components/results/QuoteResults.vue';
import { useCalculate } from '@/composables/useCalculate';
import type { CalculateRequest } from '@/domain/types';
import { es } from '@/i18n/es';

const { loading, error, data, submit, retry } = useCalculate();

async function onSubmit(payload: CalculateRequest): Promise<void> {
  await submit(payload);
}
</script>

<template>
  <main class="page">
    <h1 class="page__title">{{ es.pageTitle }}</h1>

    <RouterLink class="page__alt-link" :to="{ name: 'wizard.step1' }">
      {{ es.wizard.startLink }} →
    </RouterLink>

    <QuoteForm :loading="loading" @submit="onSubmit" />

    <QuoteResults :loading="loading" :error="error" :data="data" @retry="retry" />
  </main>
</template>
