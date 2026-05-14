import { describe, expect, it, vi } from 'vitest';
import { ApiClient, ApiError } from '@/api/client';

function jsonResponse(body: unknown, status = 200): Response {
  return new Response(JSON.stringify(body), {
    status,
    headers: { 'Content-Type': 'application/json' },
  });
}

describe('ApiClient', () => {
  it('POSTs JSON to baseUrl + path', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ ok: true }));
    const client = new ApiClient({ baseUrl: 'http://api.example/', fetchImpl });

    await client.postJson('/calculate', { foo: 'bar' });

    expect(fetchImpl).toHaveBeenCalledWith(
      'http://api.example/calculate',
      expect.objectContaining({
        method: 'POST',
        body: '{"foo":"bar"}',
        headers: expect.objectContaining({ 'Content-Type': 'application/json' }),
      }),
    );
  });

  it('returns the parsed JSON body on 2xx', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({ result: 42 }));
    const client = new ApiClient({ baseUrl: '', fetchImpl });

    const data = await client.postJson<{ result: number }>('/x', {});
    expect(data).toEqual({ result: 42 });
  });

  it('throws ApiError(network) when fetch itself rejects', async () => {
    const fetchImpl = vi.fn().mockRejectedValue(new TypeError('NetworkError'));
    const client = new ApiClient({ baseUrl: '', fetchImpl });

    await expect(client.postJson('/x', {})).rejects.toMatchObject({ kind: 'network' });
  });

  it('throws ApiError(validation) with violations on 4xx', async () => {
    const body = {
      error: 'validation_failed',
      violations: [{ field: 'car_type', message: 'invalid' }],
    };
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse(body, 400));
    const client = new ApiClient({ baseUrl: '', fetchImpl });

    const err = await client.postJson('/x', {}).catch((e) => e);
    expect(err).toBeInstanceOf(ApiError);
    expect(err.kind).toBe('validation');
    expect(err.status).toBe(400);
    expect(err.violations).toEqual(body.violations);
  });

  it('throws ApiError(server) on 5xx', async () => {
    const fetchImpl = vi.fn().mockResolvedValue(jsonResponse({}, 503));
    const client = new ApiClient({ baseUrl: '', fetchImpl });

    await expect(client.postJson('/x', {})).rejects.toMatchObject({ kind: 'server', status: 503 });
  });
});
