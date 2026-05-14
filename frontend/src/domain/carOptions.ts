import type { CarType, CarUse } from './types';

export const CAR_TYPES: readonly CarType[] = ['Turismo', 'SUV', 'Compacto'] as const;

// The UI exposes only the Spanish car-use labels; the backend tolerates "Commercial" too.
export const CAR_USES: readonly CarUse[] = ['Privado', 'Comercial'] as const;
