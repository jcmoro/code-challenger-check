import { describe, expect, it, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { createMemoryHistory, createRouter, type Router } from 'vue-router';
import WizardPage from '@/pages/wizard/WizardPage.vue';
import Step1Birthday from '@/pages/wizard/Step1Birthday.vue';
import Step2CarType from '@/pages/wizard/Step2CarType.vue';
import Step3CarUse from '@/pages/wizard/Step3CarUse.vue';
import WizardResult from '@/pages/wizard/WizardResult.vue';
import HomePage from '@/pages/HomePage.vue';

// We build our own memory-history router per test. The real `@/router` module
// is still imported transitively (WizardPage reads `slideDirection` from it),
// but its singleton guards only fire on its own router instance — they have
// no effect on the test router. That's fine; we're not testing the slide
// direction, just navigation + state.

function buildRouter(): Router {
  return createRouter({
    history: createMemoryHistory(),
    routes: [
      { path: '/', name: 'home', component: HomePage, meta: { order: 0 } },
      {
        path: '/wizard',
        component: WizardPage,
        children: [
          { path: '', redirect: { name: 'wizard.step1' } },
          { path: 'step1', name: 'wizard.step1', component: Step1Birthday, meta: { order: 1 } },
          { path: 'step2', name: 'wizard.step2', component: Step2CarType, meta: { order: 2 } },
          { path: 'step3', name: 'wizard.step3', component: Step3CarUse, meta: { order: 3 } },
          { path: 'result', name: 'wizard.result', component: WizardResult, meta: { order: 4 } },
        ],
      },
    ],
  });
}

beforeEach(() => {
  if (typeof window !== 'undefined') {
    window.sessionStorage.clear();
  }
});

describe('Wizard navigation', () => {
  it('renders step 1 (birthday field) when the user enters /wizard/step1', async () => {
    const router = buildRouter();
    router.push('/wizard/step1');
    await router.isReady();

    const wrapper = mount(WizardPage, { global: { plugins: [router] } });
    await flushPromises();

    expect(wrapper.find('input[name="driver_birthday"]').exists()).toBe(true);
    expect(wrapper.find('.wizard-shell__button--continue').attributes('disabled')).toBeDefined();
  });

  it('advances from step 1 to step 2 when Continue is clicked with a valid birthday', async () => {
    const router = buildRouter();
    router.push('/wizard/step1');
    await router.isReady();

    const wrapper = mount(WizardPage, { global: { plugins: [router] } });
    await flushPromises();

    await wrapper.get('input[name="driver_birthday"]').setValue('1992-02-24');
    await flushPromises();

    const continueBtn = wrapper.get<HTMLButtonElement>('.wizard-shell__button--continue');
    expect(continueBtn.element.disabled).toBe(false);

    await continueBtn.trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('wizard.step2');
    expect(wrapper.find('select[name="car_type"]').exists()).toBe(true);
  });

  it('renders step 3 (car-use radio) when navigated directly', async () => {
    const router = buildRouter();
    router.push('/wizard/step3');
    await router.isReady();

    const wrapper = mount(WizardPage, { global: { plugins: [router] } });
    await flushPromises();

    expect(wrapper.find('input[type="radio"][value="Privado"]').exists()).toBe(true);
    expect(wrapper.find('input[type="radio"][value="Comercial"]').exists()).toBe(true);
  });

  it('disables Continue on step 2 until a car_type is chosen', async () => {
    const router = buildRouter();
    router.push('/wizard/step2');
    await router.isReady();

    const wrapper = mount(WizardPage, { global: { plugins: [router] } });
    await flushPromises();

    const continueBtn = wrapper.get<HTMLButtonElement>('.wizard-shell__button--continue');
    expect(continueBtn.element.disabled).toBe(true);

    await wrapper.get('select[name="car_type"]').setValue('Compacto');
    await flushPromises();

    expect(continueBtn.element.disabled).toBe(false);
  });

  it('disables Continue on step 3 until a car_use is chosen', async () => {
    const router = buildRouter();
    router.push('/wizard/step3');
    await router.isReady();

    const wrapper = mount(WizardPage, { global: { plugins: [router] } });
    await flushPromises();

    const continueBtn = wrapper.get<HTMLButtonElement>('.wizard-shell__button--continue');
    expect(continueBtn.element.disabled).toBe(true);

    await wrapper.get('input[type="radio"][value="Privado"]').setValue();
    await flushPromises();

    expect(continueBtn.element.disabled).toBe(false);
  });

  it('persists data across steps via the shared form-state injection', async () => {
    const router = buildRouter();
    router.push('/wizard/step1');
    await router.isReady();

    const wrapper = mount(WizardPage, { global: { plugins: [router] } });
    await flushPromises();

    await wrapper.get('input[name="driver_birthday"]').setValue('1990-05-13');
    await wrapper.get('.wizard-shell__button--continue').trigger('click');
    await flushPromises();

    await wrapper.get('select[name="car_type"]').setValue('SUV');
    await wrapper.get('.wizard-shell__button--continue').trigger('click');
    await flushPromises();

    // Now on step 3. Going back to step 1 should still show the original birthday.
    await wrapper.get('.wizard-shell__button--back').trigger('click');
    await flushPromises();
    await wrapper.get('.wizard-shell__button--back').trigger('click');
    await flushPromises();

    expect(router.currentRoute.value.name).toBe('wizard.step1');
    const input = wrapper.get<HTMLInputElement>('input[name="driver_birthday"]');
    expect(input.element.value).toBe('1990-05-13');
  });
});
