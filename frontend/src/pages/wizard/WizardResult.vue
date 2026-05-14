<script setup lang="ts">
import { inject, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import QuoteResults from '@/components/results/QuoteResults.vue';
import { CALCULATE_KEY } from '@/composables/useCalculate';
import { FORM_STATE_KEY } from '@/composables/useFormState';
import type { CalculateRequest } from '@/domain/types';
import { es } from '@/i18n/es';

const formState = inject(FORM_STATE_KEY);
const calculate = inject(CALCULATE_KEY);
if (!formState || !calculate) throw new Error('WizardResult must be a child of WizardPage');

const { form } = formState;
const { loading, error, data, submit, retry } = calculate;

onMounted(() => {
  if (form.driver_birthday && form.car_type && form.car_use) {
    void submit({
      driver_birthday: form.driver_birthday,
      car_type: form.car_type,
      car_use: form.car_use,
    } as CalculateRequest);
  }
});
</script>

<template>
  <section class="wizard-step wizard-result">
    <h2 class="wizard-step__heading">Tus presupuestos</h2>

    <QuoteResults :loading="loading" :error="error" :data="data" @retry="retry" />

    <nav class="wizard-result__actions">
      <RouterLink class="wizard-shell__button" :to="{ name: 'wizard.step1' }">
        ← {{ es.wizard.restart }}
      </RouterLink>
      <RouterLink class="wizard-shell__button" :to="{ name: 'home' }">
        {{ es.wizard.backToSimple }}
      </RouterLink>
    </nav>
  </section>
</template>
