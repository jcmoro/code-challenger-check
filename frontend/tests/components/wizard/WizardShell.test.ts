import { describe, expect, it, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import { defineComponent, h } from 'vue';
import WizardShell from '@/components/wizard/WizardShell.vue';

const Stub = defineComponent({ render: () => h('div') });

function buildRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/wizard/step1', name: 'wizard.step1', component: Stub },
      { path: '/wizard/step2', name: 'wizard.step2', component: Stub },
      { path: '/wizard/step3', name: 'wizard.step3', component: Stub },
    ],
  });
}

let router: Router;

beforeEach(async () => {
  router = buildRouter();
  await router.push('/wizard/step2');
  await router.isReady();
});

describe('WizardShell', () => {
  it('renders progress label "Paso N de 3"', () => {
    const wrapper = mount(WizardShell, {
      props: { step: 2, canContinue: true },
      global: { plugins: [router] },
    });
    expect(wrapper.text()).toContain('Paso 2 de 3');
  });

  it('disables the Continue button when canContinue is false', () => {
    const wrapper = mount(WizardShell, {
      props: { step: 2, canContinue: false, continueTo: 'wizard.step3' },
      global: { plugins: [router] },
    });
    const cont = wrapper.get<HTMLButtonElement>('.wizard-shell__button--continue');
    expect(cont.element.disabled).toBe(true);
  });

  it('omits the Back button when backTo is null', () => {
    const wrapper = mount(WizardShell, {
      props: { step: 1, canContinue: true, continueTo: 'wizard.step2' },
      global: { plugins: [router] },
    });
    expect(wrapper.find('.wizard-shell__button--back').exists()).toBe(false);
  });

  it('renders the Back button when backTo is provided', () => {
    const wrapper = mount(WizardShell, {
      props: {
        step: 2,
        canContinue: true,
        backTo: 'wizard.step1',
        continueTo: 'wizard.step3',
      },
      global: { plugins: [router] },
    });
    expect(wrapper.find('.wizard-shell__button--back').exists()).toBe(true);
  });

  it('navigates to continueTo when Continue is clicked', async () => {
    const wrapper = mount(WizardShell, {
      props: { step: 2, canContinue: true, continueTo: 'wizard.step3' },
      global: { plugins: [router] },
    });

    await wrapper.get('.wizard-shell__button--continue').trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('wizard.step3');
  });

  it('navigates to backTo when Back is clicked', async () => {
    const wrapper = mount(WizardShell, {
      props: {
        step: 2,
        canContinue: true,
        backTo: 'wizard.step1',
        continueTo: 'wizard.step3',
      },
      global: { plugins: [router] },
    });

    await wrapper.get('.wizard-shell__button--back').trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('wizard.step1');
  });

  it('emits "continue" instead of routing when continueTo is null', async () => {
    const wrapper = mount(WizardShell, {
      props: { step: 3, canContinue: true },
      global: { plugins: [router] },
    });

    await wrapper.get('.wizard-shell__button--continue').trigger('click');

    expect(wrapper.emitted('continue')).toHaveLength(1);
    expect(router.currentRoute.value.name).toBe('wizard.step2'); // unchanged
  });

  it('does not navigate or emit when Continue is clicked while disabled', async () => {
    const wrapper = mount(WizardShell, {
      props: { step: 2, canContinue: false, continueTo: 'wizard.step3' },
      global: { plugins: [router] },
    });

    await wrapper.get('.wizard-shell__button--continue').trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('wizard.step2');
    expect(wrapper.emitted('continue')).toBeUndefined();
  });

  it('uses the continueLabel prop instead of the default when provided', () => {
    const wrapper = mount(WizardShell, {
      props: { step: 3, canContinue: true, continueLabel: 'Calcular' },
      global: { plugins: [router] },
    });

    expect(wrapper.get('.wizard-shell__button--continue').text()).toContain('Calcular');
  });
});
