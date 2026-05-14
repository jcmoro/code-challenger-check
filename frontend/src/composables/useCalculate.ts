import { ref, type InjectionKey } from 'vue';
import { postCalculate } from '@/api/calculate';
import { ApiError, type ApiClient } from '@/api/client';
import type { CalculateRequest, CalculateResponse } from '@/domain/types';

export interface UseCalculateOptions {
  client?: ApiClient;
}

export type CalculateApi = ReturnType<typeof useCalculate>;

export const CALCULATE_KEY: InjectionKey<CalculateApi> = Symbol('calculate');

export function useCalculate(options: UseCalculateOptions = {}) {
  const loading = ref(false);
  const error = ref<ApiError | null>(null);
  const data = ref<CalculateResponse | null>(null);
  const lastRequest = ref<CalculateRequest | null>(null);

  async function submit(payload: CalculateRequest): Promise<void> {
    lastRequest.value = payload;
    loading.value = true;
    error.value = null;
    try {
      data.value = await postCalculate(payload, options.client);
    } catch (cause) {
      data.value = null;
      error.value =
        cause instanceof ApiError
          ? cause
          : new ApiError('unknown', cause instanceof Error ? cause.message : String(cause));
    } finally {
      loading.value = false;
    }
  }

  async function retry(): Promise<void> {
    if (lastRequest.value) await submit(lastRequest.value);
  }

  function reset(): void {
    loading.value = false;
    error.value = null;
    data.value = null;
    lastRequest.value = null;
  }

  return { loading, error, data, submit, retry, reset };
}
