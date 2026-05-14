import { ref } from 'vue';
import { createRouter, createWebHistory, type RouteRecordRaw } from 'vue-router';
import HomePage from '@/pages/HomePage.vue';
import WizardPage from '@/pages/wizard/WizardPage.vue';
import Step1Birthday from '@/pages/wizard/Step1Birthday.vue';
import Step2CarType from '@/pages/wizard/Step2CarType.vue';
import Step3CarUse from '@/pages/wizard/Step3CarUse.vue';
import WizardResult from '@/pages/wizard/WizardResult.vue';

/**
 * The active transition direction, set by the global beforeEach guard based on
 * each route's `meta.order`. WizardPage reads this to pick the slide name
 * (forward = right-to-left, back = left-to-right, iOS-style).
 */
export type SlideDirection = 'forward' | 'back';
export const slideDirection = ref<SlideDirection>('forward');

const routes: RouteRecordRaw[] = [
  {
    path: '/',
    name: 'home',
    component: HomePage,
    meta: { order: 0 },
  },
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
];

export const router = createRouter({
  history: createWebHistory(),
  routes,
});

router.beforeEach((to, from) => {
  const toOrder = typeof to.meta?.order === 'number' ? to.meta.order : 0;
  const fromOrder = typeof from.meta?.order === 'number' ? from.meta.order : 0;
  slideDirection.value = toOrder >= fromOrder ? 'forward' : 'back';
});
