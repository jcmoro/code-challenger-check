import { apiClient, type ApiClient } from './client';
import type { CalculateRequest, CalculateResponse } from '@/domain/types';

export async function postCalculate(
  request: CalculateRequest,
  client: ApiClient = apiClient,
): Promise<CalculateResponse> {
  return client.postJson<CalculateResponse, CalculateRequest>('/calculate', request);
}
