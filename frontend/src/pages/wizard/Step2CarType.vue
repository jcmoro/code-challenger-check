<script setup lang="ts">
import { computed, inject, ref } from 'vue';
import CarTypeField from '@/components/form/CarTypeField.vue';
import WizardShell from '@/components/wizard/WizardShell.vue';
import { FORM_STATE_KEY } from '@/composables/useFormState';

const formState = inject(FORM_STATE_KEY);
if (!formState) throw new Error('Step2CarType must be a child of WizardPage');
const { form } = formState;

const error = ref<string | null>(null);

const isValid = computed(() => form.car_type !== '');

function onBlur(): void {
  error.value = isValid.value ? null : 'Selecciona un tipo de coche.';
}
</script>

<template>
  <section class="wizard-step">
    <h2 class="wizard-step__heading">¿Qué tipo de coche tienes?</h2>
    <CarTypeField v-model="form.car_type" :error="error" required @blur="onBlur" />
    <WizardShell
      :step="2"
      :can-continue="isValid"
      back-to="wizard.step1"
      continue-to="wizard.step3"
    />
  </section>
</template>
