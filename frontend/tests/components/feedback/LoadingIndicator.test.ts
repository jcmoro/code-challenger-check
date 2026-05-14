import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import LoadingIndicator from '@/components/feedback/LoadingIndicator.vue';

describe('LoadingIndicator', () => {
  it('renders a Spanish progress message inside an aria-live region', () => {
    const wrapper = mount(LoadingIndicator);

    expect(wrapper.text().length).toBeGreaterThan(0);
    expect(wrapper.attributes('role')).toBe('status');
    expect(wrapper.attributes('aria-live')).toBe('polite');
  });
});
