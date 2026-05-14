<script setup lang="ts">
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { es } from '@/i18n/es';

interface Props {
  step: number;
  totalSteps?: number;
  canContinue: boolean;
  backTo?: string | null;
  continueTo?: string | null;
  continueLabel?: string;
}
const props = withDefaults(defineProps<Props>(), {
  totalSteps: 3,
  backTo: null,
  continueTo: null,
  continueLabel: '',
});
const emit = defineEmits<{
  continue: [];
}>();
const router = useRouter();

const label = computed(() =>
  es.wizard.stepLabel
    .replace('{current}', String(props.step))
    .replace('{total}', String(props.totalSteps)),
);

const continueText = computed(() => props.continueLabel || es.wizard.continue);

function goBack(): void {
  if (props.backTo) {
    void router.push({ name: props.backTo });
  }
}

function goContinue(): void {
  if (!props.canContinue) return;
  if (props.continueTo) {
    void router.push({ name: props.continueTo });
  } else {
    emit('continue');
  }
}
</script>

<template>
  <nav class="wizard-shell" :aria-label="label">
    <p class="wizard-shell__progress">{{ label }}</p>
    <div class="wizard-shell__actions">
      <button
        v-if="backTo"
        type="button"
        class="wizard-shell__button wizard-shell__button--back"
        @click="goBack"
      >
        ← {{ es.wizard.back }}
      </button>
      <button
        type="button"
        class="wizard-shell__button wizard-shell__button--continue"
        :disabled="!canContinue"
        @click="goContinue"
      >
        {{ continueText }} →
      </button>
    </div>
  </nav>
</template>
