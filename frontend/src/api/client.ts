import type { Violation } from '@/domain/types';

export type ApiErrorKind = 'network' | 'validation' | 'server' | 'unknown';

export class ApiError extends Error {
  readonly kind: ApiErrorKind;
  readonly status?: number;
  readonly violations?: Violation[];

  constructor(kind: ApiErrorKind, message: string, status?: number, violations?: Violation[]) {
    super(message);
    this.name = 'ApiError';
    this.kind = kind;
    this.status = status;
    this.violations = violations;
  }
}

interface ValidationErrorEnvelope {
  error: string;
  violations?: Violation[];
}

interface ClientOptions {
  baseUrl?: string;
  fetchImpl?: typeof fetch;
}

const DEFAULT_BASE_URL = (import.meta.env.VITE_API_BASE as string | undefined) ?? '';

export class ApiClient {
  private readonly baseUrl: string;
  private readonly fetchImpl: typeof fetch;

  constructor(options: ClientOptions = {}) {
    this.baseUrl = (options.baseUrl ?? DEFAULT_BASE_URL).replace(/\/$/, '');
    this.fetchImpl = options.fetchImpl ?? globalThis.fetch.bind(globalThis);
  }

  async postJson<TResponse, TBody = unknown>(path: string, body: TBody): Promise<TResponse> {
    let response: Response;
    try {
      response = await this.fetchImpl(`${this.baseUrl}${path}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
      });
    } catch (cause) {
      throw new ApiError(
        'network',
        cause instanceof Error ? cause.message : 'Network request failed',
      );
    }

    if (response.ok) {
      return (await response.json()) as TResponse;
    }

    if (response.status >= 400 && response.status < 500) {
      const envelope = await this.safeJson<ValidationErrorEnvelope>(response);
      throw new ApiError(
        'validation',
        envelope?.error ?? 'Validation failed',
        response.status,
        envelope?.violations,
      );
    }

    throw new ApiError('server', `Upstream returned HTTP ${response.status}`, response.status);
  }

  private async safeJson<T>(response: Response): Promise<T | undefined> {
    try {
      return (await response.json()) as T;
    } catch {
      return undefined;
    }
  }
}

// Module-level singleton — convenient default; tests construct their own.
export const apiClient = new ApiClient();
