import { describe, expect, it, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import QuoteForm from '@/components/form/QuoteForm.vue';

beforeEach(() => {
  // Force a clean slate between tests since useFormState persists to sessionStorage.
  if (typeof window !== 'undefined') {
    window.sessionStorage.clear();
  }
});

describe('QuoteForm', () => {
  it('starts with the submit button disabled (no fields filled)', () => {
    const wrapper = mount(QuoteForm);

    const submit = wrapper.get<HTMLButtonElement>('button[type="submit"]');
    expect(submit.element.disabled).toBe(true);
  });

  it('enables submit once all three valid fields are filled', async () => {
    const wrapper = mount(QuoteForm);

    await wrapper.get('input[name="driver_birthday"]').setValue('1992-02-24');
    await wrapper.get('select[name="car_type"]').setValue('Turismo');
    await wrapper.get('input[type="radio"][value="Privado"]').setValue();

    const submit = wrapper.get<HTMLButtonElement>('button[type="submit"]');
    expect(submit.element.disabled).toBe(false);
  });

  it('keeps submit disabled when loading prop is true (even with valid fields)', async () => {
    const wrapper = mount(QuoteForm, { props: { loading: true } });

    await wrapper.get('input[name="driver_birthday"]').setValue('1992-02-24');
    await wrapper.get('select[name="car_type"]').setValue('SUV');
    await wrapper.get('input[type="radio"][value="Privado"]').setValue();

    const submit = wrapper.get<HTMLButtonElement>('button[type="submit"]');
    expect(submit.element.disabled).toBe(true);
  });

  it('emits submit with the typed payload when valid and submitted', async () => {
    const wrapper = mount(QuoteForm);

    await wrapper.get('input[name="driver_birthday"]').setValue('1992-02-24');
    await wrapper.get('select[name="car_type"]').setValue('Compacto');
    await wrapper.get('input[type="radio"][value="Comercial"]').setValue();
    await wrapper.get('form').trigger('submit');

    expect(wrapper.emitted('submit')).toBeTruthy();
    expect(wrapper.emitted('submit')![0][0]).toEqual({
      driver_birthday: '1992-02-24',
      car_type: 'Compacto',
      car_use: 'Comercial',
    });
  });

  it('rejects an under-18 birthday with an inline error', async () => {
    const wrapper = mount(QuoteForm);

    await wrapper.get('input[name="driver_birthday"]').setValue('2020-01-01');
    await wrapper.get('input[name="driver_birthday"]').trigger('blur');

    expect(wrapper.text()).toContain('18 años');
  });

  it('clears a previously-shown error when the field becomes valid', async () => {
    const wrapper = mount(QuoteForm);

    await wrapper.get('input[name="driver_birthday"]').trigger('blur');
    expect(wrapper.text()).toContain('Introduce tu fecha de nacimiento');

    await wrapper.get('input[name="driver_birthday"]').setValue('1990-05-13');
    await wrapper.get('input[name="driver_birthday"]').trigger('blur');

    expect(wrapper.text()).not.toContain('Introduce tu fecha de nacimiento');
  });
});
