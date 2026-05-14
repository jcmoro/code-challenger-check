import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import SortToggle from '@/components/results/SortToggle.vue';

describe('SortToggle', () => {
  it('renders an up arrow for ascending direction', () => {
    const wrapper = mount(SortToggle, { props: { direction: 'asc' } });
    expect(wrapper.text()).toContain('↑');
    expect(wrapper.text()).not.toContain('↓');
  });

  it('renders a down arrow for descending direction', () => {
    const wrapper = mount(SortToggle, { props: { direction: 'desc' } });
    expect(wrapper.text()).toContain('↓');
    expect(wrapper.text()).not.toContain('↑');
  });

  it('reflects direction in aria-pressed', () => {
    const ascWrapper = mount(SortToggle, { props: { direction: 'asc' } });
    const descWrapper = mount(SortToggle, { props: { direction: 'desc' } });

    expect(ascWrapper.attributes('aria-pressed')).toBe('false');
    expect(descWrapper.attributes('aria-pressed')).toBe('true');
  });

  it('uses a different aria-label per direction', () => {
    const ascWrapper = mount(SortToggle, { props: { direction: 'asc' } });
    const descWrapper = mount(SortToggle, { props: { direction: 'desc' } });

    expect(ascWrapper.attributes('aria-label')).not.toBe(
      descWrapper.attributes('aria-label'),
    );
    expect(ascWrapper.attributes('aria-label')).toBeDefined();
  });

  it('emits "toggle" when clicked', async () => {
    const wrapper = mount(SortToggle, { props: { direction: 'asc' } });

    await wrapper.trigger('click');

    expect(wrapper.emitted('toggle')).toHaveLength(1);
  });
});
