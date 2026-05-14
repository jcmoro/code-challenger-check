<script setup lang="ts">
import { computed } from 'vue';
import { es } from '@/i18n/es';

interface Props {
  modelValue: string;
  error?: string | null;
  required?: boolean;
}
const props = defineProps<Props>();
const emit = defineEmits<{
  'update:modelValue': [value: string];
  blur: [];
}>();

function isoDate(d: Date): string {
  const y = d.getFullYear();
  const m = String(d.getMonth() + 1).padStart(2, '0');
  const day = String(d.getDate()).padStart(2, '0');
  return `${y}-${m}-${day}`;
}

const today = computed(() => isoDate(new Date()));
const earliest = computed(() => {
  const d = new Date();
  d.setFullYear(d.getFullYear() - 120);
  return isoDate(d);
});

const ariaInvalid = computed(() => (props.error ? 'true' : 'false'));
</script>

<template>
  <label class="field">
    <span class="field__label">{{ es.form.driverBirthday }}</span>
    <input
      class="field__input"
      type="date"
      name="driver_birthday"
      :value="modelValue"
      :max="today"
      :min="earliest"
      :required="required"
      :aria-invalid="ariaInvalid"
      @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)"
      @blur="emit('blur')"
    />
    <small v-if="error" class="field__error" role="alert">{{ error }}</small>
  </label>
</template>
