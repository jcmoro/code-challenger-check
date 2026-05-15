import { getCurrentInstance, onBeforeUnmount, reactive, watch, type InjectionKey } from 'vue';
import type { CarType, CarUse } from '@/domain/types';

/**
 * Lives in sessionStorage only — survives reload, dies on tab close, per
 * docs/plan/specification.md §4.3.
 */
const STORAGE_KEY = 'quote-form-v1';

export interface FormState {
  driver_birthday: string;
  car_type: CarType | '';
  car_use: CarUse | '';
}

export function emptyForm(): FormState {
  return { driver_birthday: '', car_type: '', car_use: '' };
}

export interface UseFormStateOptions {
  storage?: Storage;
}

export type FormStateApi = ReturnType<typeof useFormState>;

export const FORM_STATE_KEY: InjectionKey<FormStateApi> = Symbol('formState');

function defaultStorage(): Storage | undefined {
  return globalThis.sessionStorage ?? undefined;
}

export function useFormState(options: UseFormStateOptions = {}) {
  const storage = options.storage ?? defaultStorage();

  const form = reactive<FormState>(hydrate(storage));

  // Debounce persistence so we don't hammer storage on every keystroke.
  let persistHandle: ReturnType<typeof setTimeout> | undefined;
  function flush(): void {
    if (!storage) return;
    if (persistHandle) {
      clearTimeout(persistHandle);
      persistHandle = undefined;
    }
    persist(storage, { ...form });
  }

  watch(
    () => ({ ...form }),
    (next) => {
      if (!storage) return;
      if (persistHandle) clearTimeout(persistHandle);
      persistHandle = setTimeout(() => persist(storage, next), 200);
    },
    { deep: true },
  );

  // Persist immediately when the calling component unmounts so wizard-style
  // step transitions never lose the latest input to the 200 ms debounce window.
  if (getCurrentInstance()) {
    onBeforeUnmount(flush);
  }

  function reset(): void {
    Object.assign(form, emptyForm());
    storage?.removeItem(STORAGE_KEY);
  }

  return { form, reset, flush };
}

function hydrate(storage: Storage | undefined): FormState {
  if (!storage) return emptyForm();
  try {
    const raw = storage.getItem(STORAGE_KEY);
    if (!raw) return emptyForm();
    const parsed = JSON.parse(raw) as Partial<FormState>;
    return {
      driver_birthday: typeof parsed.driver_birthday === 'string' ? parsed.driver_birthday : '',
      car_type: parsed.car_type ?? '',
      car_use: parsed.car_use ?? '',
    };
  } catch {
    return emptyForm();
  }
}

function persist(storage: Storage, value: FormState): void {
  try {
    storage.setItem(STORAGE_KEY, JSON.stringify(value));
  } catch {
    // Quota errors / private mode — silently drop persistence.
  }
}
