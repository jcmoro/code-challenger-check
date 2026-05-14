<script setup lang="ts">
import type { CarUse } from '@/domain/types';
import { es } from '@/i18n/es';

interface Props {
  modelValue: CarUse | '';
  error?: string | null;
  required?: boolean;
}
defineProps<Props>();
const emit = defineEmits<{
  'update:modelValue': [value: CarUse | ''];
  blur: [];
}>();
</script>

<template>
  <fieldset class="field field--radio">
    <legend class="field__label">{{ es.form.carUse }}</legend>
    <label class="radio">
      <input
        type="radio"
        name="car_use"
        value="Privado"
        :checked="modelValue === 'Privado'"
        :required="required"
        @change="emit('update:modelValue', 'Privado')"
        @blur="emit('blur')"
      />
      {{ es.form.privado }}
    </label>
    <label class="radio">
      <input
        type="radio"
        name="car_use"
        value="Comercial"
        :checked="modelValue === 'Comercial'"
        @change="emit('update:modelValue', 'Comercial')"
        @blur="emit('blur')"
      />
      {{ es.form.comercial }}
    </label>
    <small v-if="error" class="field__error" role="alert">{{ error }}</small>
  </fieldset>
</template>
