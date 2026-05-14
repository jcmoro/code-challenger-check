<script setup lang="ts">
import { computed, reactive } from 'vue';
import BirthdayField from './BirthdayField.vue';
import CarTypeField from './CarTypeField.vue';
import CarUseField from './CarUseField.vue';
import { useFormState } from '@/composables/useFormState';
import type { CalculateRequest } from '@/domain/types';
import { es } from '@/i18n/es';

interface Props {
  loading?: boolean;
}
const props = defineProps<Props>();
const emit = defineEmits<{
  submit: [payload: CalculateRequest];
}>();

const { form } = useFormState();

interface FieldErrors {
  driver_birthday: string | null;
  car_type: string | null;
  car_use: string | null;
}
const errors = reactive<FieldErrors>({
  driver_birthday: null,
  car_type: null,
  car_use: null,
});

function validateBirthday(value: string): string | null {
  if (!value) return 'Introduce tu fecha de nacimiento.';
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return 'Fecha no válida.';

  const today = new Date();
  if (parsed > today) return 'La fecha no puede ser futura.';

  const age = ageInYears(parsed, today);
  if (age < 18) return 'El conductor debe tener al menos 18 años.';
  if (age > 120) return 'Introduce una fecha de nacimiento realista.';
  return null;
}

function ageInYears(birthday: Date, today: Date): number {
  let years = today.getFullYear() - birthday.getFullYear();
  const monthDelta = today.getMonth() - birthday.getMonth();
  const dayDelta = today.getDate() - birthday.getDate();
  if (monthDelta < 0 || (monthDelta === 0 && dayDelta < 0)) {
    years -= 1;
  }
  return years;
}

function validateCarType(value: string): string | null {
  return value ? null : 'Selecciona un tipo de coche.';
}

function validateCarUse(value: string): string | null {
  return value ? null : 'Indica el uso del coche.';
}

const isValid = computed(
  () =>
    validateBirthday(form.driver_birthday) === null &&
    validateCarType(form.car_type) === null &&
    validateCarUse(form.car_use) === null,
);

function checkField(field: keyof FieldErrors): void {
  switch (field) {
    case 'driver_birthday':
      errors.driver_birthday = validateBirthday(form.driver_birthday);
      break;
    case 'car_type':
      errors.car_type = validateCarType(form.car_type);
      break;
    case 'car_use':
      errors.car_use = validateCarUse(form.car_use);
      break;
  }
}

function checkAll(): void {
  checkField('driver_birthday');
  checkField('car_type');
  checkField('car_use');
}

function onSubmit(event: Event): void {
  event.preventDefault();
  checkAll();
  if (!isValid.value) return;

  emit('submit', {
    driver_birthday: form.driver_birthday,
    car_type: form.car_type as CalculateRequest['car_type'],
    car_use: form.car_use as CalculateRequest['car_use'],
  });
}
</script>

<template>
  <form class="quote-form" novalidate @submit="onSubmit">
    <BirthdayField
      v-model="form.driver_birthday"
      :error="errors.driver_birthday"
      required
      @blur="checkField('driver_birthday')"
    />
    <CarTypeField
      v-model="form.car_type"
      :error="errors.car_type"
      required
      @blur="checkField('car_type')"
    />
    <CarUseField
      v-model="form.car_use"
      :error="errors.car_use"
      required
      @blur="checkField('car_use')"
    />
    <button
      type="submit"
      class="quote-form__submit"
      :disabled="!isValid || props.loading"
      :aria-busy="props.loading ? 'true' : 'false'"
    >
      {{ props.loading ? es.form.submitting : es.form.submit }}
    </button>
  </form>
</template>
