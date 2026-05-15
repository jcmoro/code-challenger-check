import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import LoadingIndicator from '@/components/feedback/LoadingIndicator.vue';

describe('LoadingIndicator', () => {
  it('renders a Spanish progress message inside a live region (<output> + aria-live=polite)', () => {
    const wrapper = mount(LoadingIndicator);

    expect(wrapper.text().length).toBeGreaterThan(0);
    expect(wrapper.element.tagName).toBe('OUTPUT');
    expect(wrapper.attributes('aria-live')).toBe('polite');
  });
});
