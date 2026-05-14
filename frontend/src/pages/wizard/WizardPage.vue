<script setup lang="ts">
import { provide } from 'vue';
import { RouterView } from 'vue-router';
import { slideDirection } from '@/router';
import { CALCULATE_KEY, useCalculate } from '@/composables/useCalculate';
import { FORM_STATE_KEY, useFormState } from '@/composables/useFormState';

// One shared form-state and calculate-mutation across all wizard steps.
provide(FORM_STATE_KEY, useFormState());
provide(CALCULATE_KEY, useCalculate());
</script>

<template>
  <main class="page wizard">
    <div class="wizard__viewport">
      <RouterView v-slot="{ Component }">
        <Transition :name="slideDirection === 'forward' ? 'slide-forward' : 'slide-back'">
          <component :is="Component" />
        </Transition>
      </RouterView>
    </div>
  </main>
</template>
