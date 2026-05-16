// All user-facing Spanish strings live here so they can be relocated into a
// proper i18n library later without touching components.
export const es = {
  pageTitle: 'Compara el seguro de tu coche',
  form: {
    driverBirthday: 'Fecha de nacimiento',
    carType: 'Tipo de coche',
    carUse: 'Uso del coche',
    privado: 'Privado',
    comercial: 'Comercial',
    submit: 'Calcular',
    submitting: 'Calculando…',
  },
  results: {
    heading: 'Resultados',
    columnProvider: 'Proveedor',
    columnPrice: 'Precio (EUR)',
    columnDiscounted: 'Precio con descuento (EUR)',
    columnNote: 'Nota',
    cheapestBadge: 'Oferta más barata',
    empty: 'No hay ofertas disponibles.',
    campaignBanner: '¡Campaña activa! CHECK24 cubre el 5% de tu seguro.',
    sortAsc: 'Ordenar de mayor a menor',
    sortDesc: 'Ordenar de menor a mayor',
  },
  errors: {
    network: 'No se pudo conectar con el servidor. Inténtalo de nuevo.',
    server: 'El servidor tuvo un problema. Inténtalo de nuevo más tarde.',
    validation: 'Revisa los datos del formulario.',
    unknown: 'Ha ocurrido un error inesperado.',
    requestIdLabel: 'ID de referencia',
  },
  wizard: {
    startLink: 'Probar el asistente paso a paso',
    backToSimple: 'Volver al formulario simple',
    stepLabel: 'Paso {current} de {total}',
    back: 'Atrás',
    continue: 'Continuar',
    finish: 'Calcular',
    restart: 'Empezar de nuevo',
  },
} as const;
