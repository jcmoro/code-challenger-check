/**
 * Pure validation for the driver's birthday field.
 *
 * Used by both the single-page form (`QuoteForm`) and the wizard's
 * `Step1Birthday`. Returns the user-facing Spanish error message, or
 * `null` when the value is valid.
 *
 * The same rule is enforced server-side in `CalculateController` —
 * client-side validation here is purely UX (immediate feedback); the
 * backend remains the security boundary.
 */
export const MIN_AGE = 18;
export const MAX_AGE = 120;

export function validateBirthday(value: string, today: Date = new Date()): string | null {
  if (!value) return 'Introduce tu fecha de nacimiento.';

  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return 'Fecha no válida.';

  if (parsed > today) return 'La fecha no puede ser futura.';

  const age = ageInYears(parsed, today);
  if (age < MIN_AGE) return `El conductor debe tener al menos ${MIN_AGE} años.`;
  if (age > MAX_AGE) return 'Introduce una fecha de nacimiento realista.';

  return null;
}

/**
 * Years between `birthday` and `today`, accounting for whether the
 * birthday has already happened this year.
 */
export function ageInYears(birthday: Date, today: Date): number {
  let years = today.getFullYear() - birthday.getFullYear();
  const monthDelta = today.getMonth() - birthday.getMonth();
  const dayDelta = today.getDate() - birthday.getDate();
  if (monthDelta < 0 || (monthDelta === 0 && dayDelta < 0)) {
    years -= 1;
  }
  return years;
}
