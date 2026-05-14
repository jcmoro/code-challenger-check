import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CarUseField from '@/components/form/CarUseField.vue';

describe('CarUseField', () => {
  it('renders both Privado and Comercial radios', () => {
    const wrapper = mount(CarUseField, { props: { modelValue: '' } });

    expect(wrapper.find('input[type="radio"][value="Privado"]').exists()).toBe(true);
    expect(wrapper.find('input[type="radio"][value="Comercial"]').exists()).toBe(true);
  });

  it('marks the radio matching modelValue as checked', () => {
    const wrapper = mount(CarUseField, { props: { modelValue: 'Comercial' } });

    const privado = wrapper.get<HTMLInputElement>('input[type="radio"][value="Privado"]');
    const comercial = wrapper.get<HTMLInputElement>('input[type="radio"][value="Comercial"]');

    expect(privado.element.checked).toBe(false);
    expect(comercial.element.checked).toBe(true);
  });

  it('emits update:modelValue with "Privado" when that radio is selected', async () => {
    const wrapper = mount(CarUseField, { props: { modelValue: '' } });
    await wrapper.get('input[type="radio"][value="Privado"]').setValue();

    expect(wrapper.emitted('update:modelValue')).toEqual([['Privado']]);
  });

  it('emits update:modelValue with "Comercial" when that radio is selected', async () => {
    const wrapper = mount(CarUseField, { props: { modelValue: '' } });
    await wrapper.get('input[type="radio"][value="Comercial"]').setValue();

    expect(wrapper.emitted('update:modelValue')).toEqual([['Comercial']]);
  });

  it('renders the inline error in role="alert"', () => {
    const wrapper = mount(CarUseField, {
      props: { modelValue: '', error: 'select one' },
    });

    expect(wrapper.get('small[role="alert"]').text()).toBe('select one');
  });

  it('uses a fieldset with a legend (so the label associates with the radio group)', () => {
    const wrapper = mount(CarUseField, { props: { modelValue: '' } });

    expect(wrapper.find('fieldset').exists()).toBe(true);
    expect(wrapper.find('legend').text()).not.toBe('');
  });
});
