<script setup lang="ts">
import { computed, inject, ref } from 'vue';
import BirthdayField from '@/components/form/BirthdayField.vue';
import WizardShell from '@/components/wizard/WizardShell.vue';
import { FORM_STATE_KEY } from '@/composables/useFormState';

const formState = inject(FORM_STATE_KEY);
if (!formState) throw new Error('Step1Birthday must be a child of WizardPage');
const { form } = formState;

const error = ref<string | null>(null);

function validate(): string | null {
  if (!form.driver_birthday) return 'Introduce tu fecha de nacimiento.';
  const parsed = new Date(form.driver_birthday);
  if (Number.isNaN(parsed.getTime())) return 'Fecha no válida.';
  const today = new Date();
  if (parsed > today) return 'La fecha no puede ser futura.';
  let years = today.getFullYear() - parsed.getFullYear();
  if (
    today.getMonth() < parsed.getMonth() ||
    (today.getMonth() === parsed.getMonth() && today.getDate() < parsed.getDate())
  ) {
    years -= 1;
  }
  if (years < 18) return 'El conductor debe tener al menos 18 años.';
  if (years > 120) return 'Introduce una fecha de nacimiento realista.';
  return null;
}

function onBlur(): void {
  error.value = validate();
}

const isValid = computed(() => validate() === null);
</script>

<template>
  <section class="wizard-step">
    <h2 class="wizard-step__heading">¿Cuándo naciste?</h2>
    <BirthdayField v-model="form.driver_birthday" :error="error" required @blur="onBlur" />
    <WizardShell :step="1" :can-continue="isValid" continue-to="wizard.step2" />
  </section>
</template>
