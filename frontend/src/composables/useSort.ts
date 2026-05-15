import { computed, ref, type ComputedRef, type Ref } from 'vue';
import type { Quote } from '@/domain/types';

export type SortDirection = 'asc' | 'desc';

export function useSort(quotes: Ref<readonly Quote[]>, initial: SortDirection = 'asc') {
  const direction = ref<SortDirection>(initial);

  const sorted: ComputedRef<Quote[]> = computed(() => {
    const list = [...quotes.value];
    list.sort((a, b) => {
      const aPrice = a.discounted_price?.amount ?? a.price.amount;
      const bPrice = b.discounted_price?.amount ?? b.price.amount;
      const cmp = aPrice - bPrice;
      const tieBreak = a.provider.localeCompare(b.provider);
      const ordered = cmp === 0 ? tieBreak : cmp;
      return direction.value === 'asc' ? ordered : -ordered;
    });
    return list;
  });

  function toggle(): void {
    direction.value = direction.value === 'asc' ? 'desc' : 'asc';
  }

  return { direction, sorted, toggle };
}
