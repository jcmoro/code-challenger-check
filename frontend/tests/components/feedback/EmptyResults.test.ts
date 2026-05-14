import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import EmptyResults from '@/components/feedback/EmptyResults.vue';

describe('EmptyResults', () => {
  it('renders the spec-exact Spanish message', () => {
    const wrapper = mount(EmptyResults);
    expect(wrapper.text()).toBe('No hay ofertas disponibles.');
  });
});
