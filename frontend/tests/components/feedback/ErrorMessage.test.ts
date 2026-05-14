import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import ErrorMessage from '@/components/feedback/ErrorMessage.vue';
import { ApiError } from '@/api/client';

describe('ErrorMessage', () => {
  it('shows the network-error message for ApiError(kind=network)', () => {
    const wrapper = mount(ErrorMessage, {
      props: { error: new ApiError('network', 'fetch failed') },
    });
    expect(wrapper.text()).toContain('No se pudo conectar');
  });

  it('shows the server-error message for ApiError(kind=server)', () => {
    const wrapper = mount(ErrorMessage, {
      props: { error: new ApiError('server', 'oops', 502) },
    });
    expect(wrapper.text()).toContain('El servidor tuvo un problema');
  });

  it('lists violations for ApiError(kind=validation)', () => {
    const wrapper = mount(ErrorMessage, {
      props: {
        error: new ApiError('validation', 'invalid', 400, [
          { field: 'driver_birthday', message: 'must be in the past' },
        ]),
      },
    });
    expect(wrapper.text()).toContain('driver_birthday');
    expect(wrapper.text()).toContain('must be in the past');
  });

  it('emits retry when the button is clicked', async () => {
    const wrapper = mount(ErrorMessage, {
      props: { error: new ApiError('network', 'fail') },
    });
    await wrapper.get('button.error__retry').trigger('click');
    expect(wrapper.emitted('retry')).toBeTruthy();
  });
});
