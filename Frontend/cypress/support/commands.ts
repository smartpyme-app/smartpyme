/// <reference types="cypress" />

// ***********************************************
// This example commands.ts shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************

/** Acciones de menú/contexto prohibidas en listados (emisión DTE a MH). */
export const DTE_FORBIDDEN_MENU_LABELS = [
  'Emitir DTE',
  'Emitir DTE en contingencia',
] as const

declare global {
  namespace Cypress {
    interface Chainable {
      /**
       * Custom command to login a user
       * @example cy.login('user@example.com', 'password123')
       */
      login(email: string, password: string): Chainable<void>
      
      /**
       * Custom command to logout
       * @example cy.logout()
       */
      logout(): Chainable<void>

      /**
       * Abre el menú de acciones de una fila sin usar opciones de emisión DTE.
       * Permite crear/editar/ver; nunca hace clic en "Emitir DTE".
       */
      openRowActionsMenu(options?: { rowIndex?: number }): Chainable<JQuery<HTMLElement>>

      /**
       * Verifica que las opciones de emisión DTE no estén visibles en el menú abierto.
       */
      assertDteActionsNotAvailable(): Chainable<void>
    }
  }
}

Cypress.Commands.add('login', (email: string, password: string) => {
  cy.visit('/login')
  // Usar clear() y delay: 0 para evitar problemas con jQuery
  cy.get('input[name="correo"]').clear().type(email, { delay: 0, force: true })
  cy.get('input[name="password"]').clear().type(password, { delay: 0, force: true })
  cy.get('button[type="submit"]').click()
  // Wait for navigation after successful login
  cy.url({ timeout: 10000 }).should('not.include', '/login')
  // Verificar que el token se haya guardado
  cy.window().then((win) => {
    const token = win.localStorage.getItem('SP_token')
    expect(token).to.exist
  })
})

Cypress.Commands.add('logout', () => {
  // Clear localStorage and sessionStorage
  cy.clearLocalStorage()
  cy.clearCookies()
  cy.window().then((win) => {
    win.sessionStorage.clear()
  })
})

Cypress.Commands.add('openRowActionsMenu', (options: { rowIndex?: number } = {}) => {
  const index = options.rowIndex ?? 0
  cy.get('table tbody tr').eq(index).find('button, a').filter(':visible').last().click({ force: true })
})

Cypress.Commands.add('assertDteActionsNotAvailable', () => {
  DTE_FORBIDDEN_MENU_LABELS.forEach((label) => {
    cy.contains('a, button, .list-group-item', label).should('not.exist')
  })
})

export {}
