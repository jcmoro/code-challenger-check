import { describe, expect, it } from 'vitest';
import { ref } from 'vue';
import { useSort } from '@/composables/useSort';
import type { Quote } from '@/domain/types';

function quote(provider: string, price: number, discounted?: number): Quote {
  return {
    provider,
    price: { amount: price, currency: 'EUR' },
    discounted_price: discounted !== undefined ? { amount: discounted, currency: 'EUR' } : null,
    is_cheapest: false,
  };
}

describe('useSort', () => {
  it('sorts ascending by final price by default', () => {
    const quotes = ref<Quote[]>([quote('b', 310), quote('a', 295), quote('c', 200)]);
    const { sorted } = useSort(quotes);

    expect(sorted.value.map((q) => q.provider)).toEqual(['c', 'a', 'b']);
  });

  it('toggle() flips direction', () => {
    const quotes = ref<Quote[]>([quote('a', 100), quote('b', 200), quote('c', 300)]);
    const { sorted, toggle, direction } = useSort(quotes);

    toggle();

    expect(direction.value).toBe('desc');
    expect(sorted.value.map((q) => q.provider)).toEqual(['c', 'b', 'a']);

    toggle();
    expect(direction.value).toBe('asc');
    expect(sorted.value.map((q) => q.provider)).toEqual(['a', 'b', 'c']);
  });

  it('respects discounted_price when present', () => {
    const quotes = ref<Quote[]>([
      quote('a', 100, 95), // final 95
      quote('b', 80, 79), // final 79 — wins despite higher original
      quote('c', 90), // final 90
    ]);
    const { sorted } = useSort(quotes);

    expect(sorted.value.map((q) => q.provider)).toEqual(['b', 'c', 'a']);
  });

  it('breaks ties by provider id alphabetically', () => {
    const quotes = ref<Quote[]>([quote('c', 100), quote('a', 100), quote('b', 100)]);
    const { sorted } = useSort(quotes);

    expect(sorted.value.map((q) => q.provider)).toEqual(['a', 'b', 'c']);
  });
});
