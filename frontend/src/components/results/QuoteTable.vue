<script setup lang="ts">
import { computed, toRef } from 'vue';
import SortToggle from './SortToggle.vue';
import { useSort } from '@/composables/useSort';
import type { Quote } from '@/domain/types';
import { es } from '@/i18n/es';

interface Props {
  quotes: readonly Quote[];
}
const props = defineProps<Props>();

const quotesRef = toRef(props, 'quotes');
const { direction, sorted, toggle } = useSort(quotesRef);

const hasAnyDiscount = computed(() => props.quotes.some((q) => q.discounted_price !== null));

function formatEur(amount: number): string {
  return amount.toLocaleString('es-ES', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  });
}
</script>

<template>
  <table class="quote-table" aria-label="Resultados">
    <thead>
      <tr>
        <th scope="col">{{ es.results.columnProvider }}</th>
        <th scope="col">
          {{ es.results.columnPrice }}
          <SortToggle :direction="direction" @toggle="toggle" />
        </th>
        <th scope="col">{{ es.results.columnDiscounted }}</th>
        <th scope="col">{{ es.results.columnNote }}</th>
      </tr>
    </thead>
    <tbody>
      <tr
        v-for="quote in sorted"
        :key="quote.provider"
        :class="['quote-row', { 'quote-row--cheapest': quote.is_cheapest }]"
        :data-cheapest="quote.is_cheapest ? 'true' : 'false'"
      >
        <td>{{ quote.provider }}</td>
        <td :class="{ 'price--strikethrough': hasAnyDiscount && quote.discounted_price !== null }">
          {{ formatEur(quote.price.amount) }}
        </td>
        <td>
          <span v-if="quote.discounted_price" class="price--discounted">
            {{ formatEur(quote.discounted_price.amount) }}
          </span>
          <span v-else class="price--empty" aria-hidden="true">—</span>
        </td>
        <td>
          <span v-if="quote.is_cheapest" class="cheapest-badge">
            ★ {{ es.results.cheapestBadge }}
          </span>
        </td>
      </tr>
    </tbody>
  </table>
</template>
