import { describe, expect, it } from 'vitest';
import { ApiClient, ApiError } from '@/api/client';
import { useCalculate } from '@/composables/useCalculate';
import type { CalculateRequest, CalculateResponse } from '@/domain/types';

const SAMPLE_REQUEST: CalculateRequest = {
  driver_birthday: '1992-02-24',
  car_type: 'Turismo',
  car_use: 'Privado',
};

const SAMPLE_RESPONSE: CalculateResponse = {
  campaign: { active: true, percentage: 5.0 },
  quotes: [
    {
      provider: 'provider-a',
      price: { amount: 295.0, currency: 'EUR' },
      discounted_price: { amount: 280.25, currency: 'EUR' },
      is_cheapest: true,
    },
  ],
  meta: { duration_ms: 5132, failed_providers: [] },
};

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('useCalculate', () => {
  it('starts in idle state', () => {
    const { loading, error, data } = useCalculate({ client: new ApiClient({ fetchImpl: vi.fn() }) });

    expect(loading.value).toBe(false);
    expect(error.value).toBeNull();
    expect(data.value).toBeNull();
  });

  it('transitions idle → loading → success', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_RESPONSE));
    const { loading, error, data, submit } = useCalculate({
      client: new ApiClient({ fetchImpl }),
    });

    const promise = submit(SAMPLE_REQUEST);
    expect(loading.value).toBe(true);

    await promise;

    expect(loading.value).toBe(false);
    expect(error.value).toBeNull();
    expect(data.value?.quotes[0].provider).toBe('provider-a');
  });

  it('transitions idle → loading → error on a validation 400', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(
      jsonResponse(
        { error: 'validation_failed', violations: [{ field: 'driver_birthday', message: 'invalid' }] },
        400,
      ),
    );
    const { loading, error, data, submit } = useCalculate({
      client: new ApiClient({ fetchImpl }),
    });

    await submit(SAMPLE_REQUEST);

    expect(loading.value).toBe(false);
    expect(data.value).toBeNull();
    expect(error.value).toBeInstanceOf(ApiError);
    expect(error.value?.kind).toBe('validation');
    expect(error.value?.violations).toHaveLength(1);
  });

  it('transitions idle → loading → error on a 5xx', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({}, 500));
    const { error, submit } = useCalculate({ client: new ApiClient({ fetchImpl }) });

    await submit(SAMPLE_REQUEST);

    expect(error.value?.kind).toBe('server');
    expect(error.value?.status).toBe(500);
  });

  it('transitions idle → loading → error on a network failure', async () => {
    const fetchImpl = vi.fn().mockRejectedValue(new TypeError('Failed to fetch'));
    const { error, submit } = useCalculate({ client: new ApiClient({ fetchImpl }) });

    await submit(SAMPLE_REQUEST);

    expect(error.value?.kind).toBe('network');
  });

  it('reset() clears state back to idle', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse(SAMPLE_RESPONSE));
    const { data, reset, submit } = useCalculate({ client: new ApiClient({ fetchImpl }) });

    await submit(SAMPLE_REQUEST);
    expect(data.value).not.toBeNull();

    reset();
    expect(data.value).toBeNull();
  });
});
