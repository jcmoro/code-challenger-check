import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CampaignBanner from '@/components/results/CampaignBanner.vue';

describe('CampaignBanner', () => {
  it('renders the default 5% banner verbatim from i18n', () => {
    const wrapper = mount(CampaignBanner, { props: { percentage: 5 } });
    expect(wrapper.text()).toContain('5%');
    expect(wrapper.text()).toContain('CHECK24');
  });

  it('substitutes the percentage prop into the banner', () => {
    const wrapper = mount(CampaignBanner, { props: { percentage: 12 } });
    expect(wrapper.text()).toContain('12%');
    expect(wrapper.text()).not.toContain('5%');
  });

  it('renders as <output> so screen readers announce updates politely (implicit role=status)', () => {
    const wrapper = mount(CampaignBanner, { props: { percentage: 5 } });
    expect(wrapper.element.tagName).toBe('OUTPUT');
  });
});
