import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import QuoteTable from '@/components/results/QuoteTable.vue';
import type { Quote } from '@/domain/types';

function quote(provider: string, price: number, discounted?: number, cheapest = false): Quote {
  return {
    provider,
    price: { amount: price, currency: 'EUR' },
    discounted_price: discounted !== undefined ? { amount: discounted, currency: 'EUR' } : null,
    is_cheapest: cheapest,
  };
}

describe('QuoteTable', () => {
  it('renders one row per quote', () => {
    const wrapper = mount(QuoteTable, {
      props: {
        quotes: [quote('provider-a', 295), quote('provider-b', 310)],
      },
    });

    expect(wrapper.findAll('tbody tr')).toHaveLength(2);
  });

  it('marks the cheapest row with the row class and badge', () => {
    const wrapper = mount(QuoteTable, {
      props: {
        quotes: [quote('provider-a', 295, 280.25, true), quote('provider-b', 310, 294.5)],
      },
    });

    const rows = wrapper.findAll('tbody tr');
    expect(rows[0].classes()).toContain('quote-row--cheapest');
    expect(rows[1].classes()).not.toContain('quote-row--cheapest');
    expect(rows[0].text()).toContain('★');
  });

  it('formats prices with two decimals using a Spanish locale (comma)', () => {
    const wrapper = mount(QuoteTable, {
      props: { quotes: [quote('provider-a', 295)] },
    });

    expect(wrapper.find('tbody td:nth-child(2)').text()).toBe('295,00');
  });

  it('renders the discounted_price column when present and an em-dash placeholder when null', () => {
    const wrapper = mount(QuoteTable, {
      props: {
        quotes: [
          quote('provider-a', 295, 280.25), // has discount
          quote('provider-b', 310), // no discount
        ],
      },
    });

    const rows = wrapper.findAll('tbody tr');
    expect(rows[0].find('.price--discounted').exists()).toBe(true);
    expect(rows[1].find('.price--empty').exists()).toBe(true);
  });

  it('toggles sort direction when the SortToggle is clicked', async () => {
    const wrapper = mount(QuoteTable, {
      props: {
        quotes: [quote('a', 100), quote('b', 200), quote('c', 300)],
      },
    });
    const providerCells = () =>
      wrapper.findAll('tbody tr').map((r) => r.find('td:first-child').text());

    expect(providerCells()).toEqual(['a', 'b', 'c']);

    await wrapper.get('.sort-toggle').trigger('click');

    expect(providerCells()).toEqual(['c', 'b', 'a']);
  });
});
