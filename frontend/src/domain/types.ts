// TypeScript mirrors of the backend's JSON contract.
// Source of truth: docs/plan/specification.md §2.1.

export type CarType = 'Turismo' | 'SUV' | 'Compacto';

// The backend accepts the English label "Commercial" as a synonym of "Comercial"
// (see docs/plan/specification.md §1.2). The UI only offers the Spanish forms.
export type CarUse = 'Privado' | 'Comercial' | 'Commercial';

export interface CalculateRequest {
  driver_birthday: string; // ISO-8601 date, YYYY-MM-DD
  car_type: CarType;
  car_use: CarUse;
}

export interface Money {
  amount: number;
  currency: string;
}

export interface Quote {
  provider: string;
  price: Money;
  discounted_price: Money | null;
  is_cheapest: boolean;
}

export interface Campaign {
  active: boolean;
  percentage: number;
}

export interface CalculateMeta {
  duration_ms: number;
  failed_providers: string[];
}

export interface CalculateResponse {
  campaign: Campaign;
  quotes: Quote[];
  meta: CalculateMeta;
}

export interface Violation {
  field: string;
  message: string;
}

export interface ValidationErrorBody {
  error: 'validation_failed';
  violations: Violation[];
}
