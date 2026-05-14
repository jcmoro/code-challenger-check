import { describe, expect, it, beforeEach } from 'vitest';
import { nextTick } from 'vue';
import { useFormState, emptyForm, type FormState } from '@/composables/useFormState';

class InMemoryStorage implements Storage {
  private data = new Map<string, string>();

  get length(): number {
    return this.data.size;
  }
  clear(): void {
    this.data.clear();
  }
  getItem(key: string): string | null {
    return this.data.get(key) ?? null;
  }
  key(index: number): string | null {
    return Array.from(this.data.keys())[index] ?? null;
  }
  removeItem(key: string): void {
    this.data.delete(key);
  }
  setItem(key: string, value: string): void {
    this.data.set(key, value);
  }
}

async function flushDebouncedWrite(): Promise<void> {
  // Debounce window is 200 ms — wait slightly longer.
  await new Promise((resolve) => setTimeout(resolve, 250));
  await nextTick();
}

describe('useFormState', () => {
  let storage: InMemoryStorage;

  beforeEach(() => {
    storage = new InMemoryStorage();
  });

  it('hydrates with empty defaults when storage is empty', () => {
    const { form } = useFormState({ storage });

    expect(form.driver_birthday).toBe('');
    expect(form.car_type).toBe('');
    expect(form.car_use).toBe('');
  });

  it('hydrates from sessionStorage when a snapshot is present', () => {
    const previous: FormState = {
      driver_birthday: '1992-02-24',
      car_type: 'SUV',
      car_use: 'Privado',
    };
    storage.setItem('quote-form-v1', JSON.stringify(previous));

    const { form } = useFormState({ storage });

    expect(form.driver_birthday).toBe('1992-02-24');
    expect(form.car_type).toBe('SUV');
    expect(form.car_use).toBe('Privado');
  });

  it('persists changes to storage (debounced)', async () => {
    const { form } = useFormState({ storage });

    form.driver_birthday = '1980-01-01';
    form.car_type = 'Turismo';
    form.car_use = 'Comercial';

    await flushDebouncedWrite();

    const persisted = JSON.parse(storage.getItem('quote-form-v1') ?? '{}');
    expect(persisted).toEqual({
      driver_birthday: '1980-01-01',
      car_type: 'Turismo',
      car_use: 'Comercial',
    });
  });

  it('reset() clears both the reactive state and the storage entry', async () => {
    const { form, reset } = useFormState({ storage });
    form.driver_birthday = '1990-05-13';
    await flushDebouncedWrite();
    expect(storage.getItem('quote-form-v1')).not.toBeNull();

    reset();

    expect(form).toEqual(emptyForm());
    expect(storage.getItem('quote-form-v1')).toBeNull();
  });

  it('ignores corrupt JSON in storage and falls back to defaults', () => {
    storage.setItem('quote-form-v1', '{not valid json');

    const { form } = useFormState({ storage });

    expect(form).toEqual(emptyForm());
  });
});
