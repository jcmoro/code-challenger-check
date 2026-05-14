<script setup lang="ts">
import { computed } from 'vue';
import { CAR_TYPES } from '@/domain/carOptions';
import type { CarType } from '@/domain/types';
import { es } from '@/i18n/es';

interface Props {
  modelValue: CarType | '';
  error?: string | null;
  required?: boolean;
}
const props = defineProps<Props>();
const emit = defineEmits<{
  'update:modelValue': [value: CarType | ''];
  blur: [];
}>();

const ariaInvalid = computed(() => (props.error ? 'true' : 'false'));
</script>

<template>
  <label class="field">
    <span class="field__label">{{ es.form.carType }}</span>
    <select
      class="field__input"
      name="car_type"
      :value="modelValue"
      :required="required"
      :aria-invalid="ariaInvalid"
      @change="
        emit('update:modelValue', ($event.target as HTMLSelectElement).value as CarType | '')
      "
      @blur="emit('blur')"
    >
      <option value="" disabled>—</option>
      <option v-for="option in CAR_TYPES" :key="option" :value="option">
        {{ option }}
      </option>
    </select>
    <small v-if="error" class="field__error" role="alert">{{ error }}</small>
  </label>
</template>
