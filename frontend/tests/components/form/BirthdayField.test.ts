import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BirthdayField from '@/components/form/BirthdayField.vue';

function isoToday(): string {
  const d = new Date();
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

function isoTodayMinusYears(years: number): string {
  const d = new Date();
  d.setFullYear(d.getFullYear() - years);
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

describe('BirthdayField', () => {
  it('renders the current value via the input element', () => {
    const wrapper = mount(BirthdayField, { props: { modelValue: '1992-02-24' } });
    const input = wrapper.get<HTMLInputElement>('input[name="driver_birthday"]');
    expect(input.element.value).toBe('1992-02-24');
  });

  it('caps the max attribute at today', () => {
    const wrapper = mount(BirthdayField, { props: { modelValue: '' } });
    const input = wrapper.get<HTMLInputElement>('input[name="driver_birthday"]');
    expect(input.attributes('max')).toBe(isoToday());
  });

  it('caps the min attribute at today − 120 years', () => {
    const wrapper = mount(BirthdayField, { props: { modelValue: '' } });
    const input = wrapper.get<HTMLInputElement>('input[name="driver_birthday"]');
    expect(input.attributes('min')).toBe(isoTodayMinusYears(120));
  });

  it('emits update:modelValue on input', async () => {
    const wrapper = mount(BirthdayField, { props: { modelValue: '' } });
    await wrapper.get('input[name="driver_birthday"]').setValue('1990-05-13');

    expect(wrapper.emitted('update:modelValue')).toEqual([['1990-05-13']]);
  });

  it('emits "blur" on blur', async () => {
    const wrapper = mount(BirthdayField, { props: { modelValue: '' } });
    await wrapper.get('input[name="driver_birthday"]').trigger('blur');

    expect(wrapper.emitted('blur')).toHaveLength(1);
  });

  it('renders the error in a role="alert" small element when present', () => {
    const wrapper = mount(BirthdayField, {
      props: { modelValue: '', error: 'must be at least 18' },
    });

    const error = wrapper.get('small[role="alert"]');
    expect(error.text()).toBe('must be at least 18');
  });

  it('reflects error state in aria-invalid', () => {
    const ok = mount(BirthdayField, { props: { modelValue: '1992-02-24' } });
    const bad = mount(BirthdayField, { props: { modelValue: '', error: 'required' } });

    expect(ok.get('input[name="driver_birthday"]').attributes('aria-invalid')).toBe('false');
    expect(bad.get('input[name="driver_birthday"]').attributes('aria-invalid')).toBe('true');
  });
});
