<script setup lang="ts">
import { computed, inject, ref } from 'vue';
import CarUseField from '@/components/form/CarUseField.vue';
import WizardShell from '@/components/wizard/WizardShell.vue';
import { FORM_STATE_KEY } from '@/composables/useFormState';
import { es } from '@/i18n/es';

const formState = inject(FORM_STATE_KEY);
if (!formState) throw new Error('Step3CarUse must be a child of WizardPage');
const { form } = formState;

const error = ref<string | null>(null);

const isValid = computed(() => form.car_use !== '');

function onBlur(): void {
  error.value = isValid.value ? null : 'Indica el uso del coche.';
}
</script>

<template>
  <section class="wizard-step">
    <h2 class="wizard-step__heading">¿Para qué usas el coche?</h2>
    <CarUseField v-model="form.car_use" :error="error" required @blur="onBlur" />
    <WizardShell
      :step="3"
      :can-continue="isValid"
      back-to="wizard.step2"
      continue-to="wizard.result"
      :continue-label="es.wizard.finish"
    />
  </section>
</template>
