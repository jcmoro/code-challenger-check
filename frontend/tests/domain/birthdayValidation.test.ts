import { describe, expect, it } from 'vitest';
import { ageInYears, MAX_AGE, MIN_AGE, validateBirthday } from '@/domain/birthdayValidation';

const TODAY = new Date('2026-05-15T12:00:00Z');

describe('validateBirthday', () => {
  it('rejects an empty string', () => {
    expect(validateBirthday('', TODAY)).toBe('Introduce tu fecha de nacimiento.');
  });

  it('rejects a non-parseable date', () => {
    expect(validateBirthday('not a date', TODAY)).toBe('Fecha no válida.');
  });

  it('rejects a future date', () => {
    expect(validateBirthday('2030-01-01', TODAY)).toBe('La fecha no puede ser futura.');
  });

  it('rejects an under-18 driver', () => {
    expect(validateBirthday('2020-01-01', TODAY)).toBe(
      `El conductor debe tener al menos ${MIN_AGE} años.`,
    );
  });

  it('rejects an unrealistic age (> 120)', () => {
    expect(validateBirthday('1900-01-01', TODAY)).toBe('Introduce una fecha de nacimiento realista.');
  });

  it('accepts a valid adult birthday', () => {
    expect(validateBirthday('1992-02-24', TODAY)).toBeNull();
  });

  it('accepts the exact-18-years-ago boundary', () => {
    // today − 18 years exactly → age 18 → valid
    expect(validateBirthday('2008-05-15', TODAY)).toBeNull();
  });

  it('rejects one day before the 18th birthday', () => {
    // today − 18 years + 1 day → age 17 → invalid
    expect(validateBirthday('2008-05-16', TODAY)).toBe(
      `El conductor debe tener al menos ${MIN_AGE} años.`,
    );
  });

  it('accepts the exact-120-years-ago boundary', () => {
    expect(validateBirthday('1906-05-15', TODAY)).toBeNull();
  });

  it('rejects one day before the 120-year limit (age 121)', () => {
    expect(validateBirthday('1905-05-14', TODAY)).toBe(
      'Introduce una fecha de nacimiento realista.',
    );
  });
});

describe('ageInYears', () => {
  it('returns the full year diff when today is past the birthday', () => {
    expect(ageInYears(new Date('1992-02-24'), TODAY)).toBe(34);
  });

  it('subtracts 1 when the birthday has not yet happened this year', () => {
    expect(ageInYears(new Date('1992-06-01'), TODAY)).toBe(33);
  });

  it('returns 0 for a same-day birthday', () => {
    expect(ageInYears(new Date('2026-05-15'), TODAY)).toBe(0);
  });

  it('uses MAX_AGE/MIN_AGE constants — sanity', () => {
    expect(MIN_AGE).toBe(18);
    expect(MAX_AGE).toBe(120);
  });
});
