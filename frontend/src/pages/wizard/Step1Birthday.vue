<script setup lang="ts">
import { computed, inject, ref } from 'vue';
import BirthdayField from '@/components/form/BirthdayField.vue';
import WizardShell from '@/components/wizard/WizardShell.vue';
import { FORM_STATE_KEY } from '@/composables/useFormState';
import { validateBirthday } from '@/domain/birthdayValidation';

const formState = inject(FORM_STATE_KEY);
if (!formState) throw new Error('Step1Birthday must be a child of WizardPage');
const { form } = formState;

const error = ref<string | null>(null);

const isValid = computed(() => validateBirthday(form.driver_birthday) === null);

function onBlur(): void {
  error.value = validateBirthday(form.driver_birthday);
}
</script>

<template>
  <section class="wizard-step">
    <h2 class="wizard-step__heading">¿Cuándo naciste?</h2>
    <BirthdayField v-model="form.driver_birthday" :error="error" required @blur="onBlur" />
    <WizardShell :step="1" :can-continue="isValid" continue-to="wizard.step2" />
  </section>
</template>
