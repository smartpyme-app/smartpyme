/// <reference types="cypress" />

/** Reutiliza sesión autenticada en suites de inventario. */
export function inventarioSession(): void {
  const testEmail = Cypress.env('TEST_EMAIL') as string
  const testPassword = Cypress.env('TEST_PASSWORD') as string
  cy.session([testEmail, testPassword], () => {
    cy.login(testEmail, testPassword)
  })
}

/** Tabs compartidos entre productos, ajustes, traslados, entradas y salidas. */
export const INVENTARIO_OPERACION_TABS = [
  'Todos',
  'Ajustes',
  'Traslados',
  'Consignas',
  'Otras Entradas',
  'Otras Salidas',
] as const

/** Área principal (excluye sidebar) para evitar inputs duplicados. */
export function areaContenido(): Cypress.Chainable<JQuery<HTMLElement>> {
  return cy.get('section.bg-white.p-4, section.bg-white.p-2').first()
}

/** Buscador de operaciones de inventario (ajustes/traslados). */
export function buscarOperacionInventario(texto: string): void {
  areaContenido()
    .find('input[name="search"]')
    .first()
    .clear()
    .type(texto, { delay: 0 })
    .type('{enter}')
}

/** Botón filtrar en página de productos (sin data-cy). */
export function abrirFiltrosProductos(): void {
  cy.get('#tour.toolbar button.tcla-F3').click()
}

/** Busca producto en kardex (typeahead ng-select, mín. 2 caracteres). */
export function buscarProductoKardex(termino: string): void {
  cy.intercept('POST', '**/api/productos/buscar-modal').as('buscarProductoKardex')
  cy.get('ng-select[name="producto_kardex"]').click()
  cy.get('ng-select[name="producto_kardex"] input').clear().type(termino, { delay: 30 })
  cy.wait('@buscarProductoKardex', { timeout: 15000 })
}

/** Verifica página de lotes (activa o mensaje de módulo desactivado). */
export function assertPaginaLotes(callbackActiva: () => void): void {
  cy.visit('/lotes')
  cy.get('body', { timeout: 15000 }).then(($body) => {
    if ($body.text().includes('El módulo de lotes no está activado')) {
      cy.contains('El módulo de lotes no está activado').should('be.visible')
      cy.log('Módulo de lotes desactivado en empresa demo — smoke de aviso OK')
    } else {
      callbackActiva()
    }
  })
}

/** Modal visible más reciente (soporta modales anidados). */
export function modalVisible(): Cypress.Chainable<JQuery<HTMLElement>> {
  return cy.get('modal-container.modal.show, .modal.show').last()
}
