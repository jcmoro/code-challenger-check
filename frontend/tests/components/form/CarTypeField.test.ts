import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import CarTypeField from '@/components/form/CarTypeField.vue';

describe('CarTypeField', () => {
  it('renders one option per supported car type', () => {
    const wrapper = mount(CarTypeField, { props: { modelValue: '' } });

    const options = wrapper.findAll('option').map((o) => o.attributes('value'));
    // The placeholder option ('') comes first; the next three are the real values.
    expect(options).toEqual(['', 'Turismo', 'SUV', 'Compacto']);
  });

  it('reflects modelValue as the selected option', () => {
    const wrapper = mount(CarTypeField, { props: { modelValue: 'SUV' } });
    const select = wrapper.get<HTMLSelectElement>('select[name="car_type"]');
    expect(select.element.value).toBe('SUV');
  });

  it('emits update:modelValue when the user picks an option', async () => {
    const wrapper = mount(CarTypeField, { props: { modelValue: '' } });
    await wrapper.get('select[name="car_type"]').setValue('Compacto');

    expect(wrapper.emitted('update:modelValue')).toEqual([['Compacto']]);
  });

  it('emits "blur" on blur', async () => {
    const wrapper = mount(CarTypeField, { props: { modelValue: '' } });
    await wrapper.get('select[name="car_type"]').trigger('blur');

    expect(wrapper.emitted('blur')).toHaveLength(1);
  });

  it('renders the inline error and aria-invalid="true" when error is present', () => {
    const wrapper = mount(CarTypeField, {
      props: { modelValue: '', error: 'pick one' },
    });

    expect(wrapper.get('small[role="alert"]').text()).toBe('pick one');
    expect(wrapper.get('select[name="car_type"]').attributes('aria-invalid')).toBe('true');
  });
});
