import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import QuoteResults from '@/components/results/QuoteResults.vue';
import { ApiError } from '@/api/client';
import type { CalculateResponse, Quote } from '@/domain/types';

function quote(provider: string, price: number, isCheapest = false): Quote {
  return {
    provider,
    price: { amount: price, currency: 'EUR' },
    discounted_price: null,
    is_cheapest: isCheapest,
  };
}

function response(opts: {
  campaignActive?: boolean;
  quotes?: Quote[];
  failedProviders?: string[];
}): CalculateResponse {
  return {
    campaign: { active: opts.campaignActive ?? false, percentage: 5.0 },
    quotes: opts.quotes ?? [],
    meta: { duration_ms: 1000, failed_providers: opts.failedProviders ?? [] },
  };
}

describe('QuoteResults', () => {
  it('shows the loading indicator while loading is true', () => {
    const wrapper = mount(QuoteResults, {
      props: { loading: true, error: null, data: null },
    });

    expect(wrapper.find('.loading').exists()).toBe(true);
    expect(wrapper.find('.quote-table').exists()).toBe(false);
    expect(wrapper.find('.empty-results').exists()).toBe(false);
    expect(wrapper.find('.error').exists()).toBe(false);
  });

  it('shows an ErrorMessage when there is an error and no loading', () => {
    const wrapper = mount(QuoteResults, {
      props: {
        loading: false,
        error: new ApiError('network', 'fetch failed'),
        data: null,
      },
    });

    expect(wrapper.find('.error').exists()).toBe(true);
    expect(wrapper.find('.loading').exists()).toBe(false);
    expect(wrapper.find('.quote-table').exists()).toBe(false);
  });

  it('renders the quote table when data has at least one quote', () => {
    const wrapper = mount(QuoteResults, {
      props: {
        loading: false,
        error: null,
        data: response({ quotes: [quote('provider-a', 295, true)] }),
      },
    });

    expect(wrapper.find('.quote-table').exists()).toBe(true);
    expect(wrapper.find('.empty-results').exists()).toBe(false);
  });

  it('renders the empty-results message when data has zero quotes', () => {
    const wrapper = mount(QuoteResults, {
      props: { loading: false, error: null, data: response({ quotes: [] }) },
    });

    expect(wrapper.find('.empty-results').exists()).toBe(true);
    expect(wrapper.find('.empty-results').text()).toBe('No hay ofertas disponibles.');
    expect(wrapper.find('.quote-table').exists()).toBe(false);
  });

  it('shows the campaign banner when data.campaign.active is true', () => {
    const wrapper = mount(QuoteResults, {
      props: {
        loading: false,
        error: null,
        data: response({ campaignActive: true, quotes: [quote('provider-a', 295, true)] }),
      },
    });

    expect(wrapper.find('.campaign-banner').exists()).toBe(true);
  });

  it('hides the campaign banner when data.campaign.active is false', () => {
    const wrapper = mount(QuoteResults, {
      props: {
        loading: false,
        error: null,
        data: response({ campaignActive: false, quotes: [quote('provider-a', 295, true)] }),
      },
    });

    expect(wrapper.find('.campaign-banner').exists()).toBe(false);
  });

  it('forwards the "retry" event from ErrorMessage', async () => {
    const wrapper = mount(QuoteResults, {
      props: {
        loading: false,
        error: new ApiError('server', 'oops', 502),
        data: null,
      },
    });

    await wrapper.get('button.error__retry').trigger('click');

    expect(wrapper.emitted('retry')).toHaveLength(1);
  });

  it('uses an aria-live region so screen readers announce updates', () => {
    const wrapper = mount(QuoteResults, {
      props: { loading: true, error: null, data: null },
    });

    expect(wrapper.attributes('aria-live')).toBe('polite');
  });
});
