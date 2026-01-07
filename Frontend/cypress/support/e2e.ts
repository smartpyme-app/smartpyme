// ***********************************************************
// This example support/e2e.ts is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'

// Alternatively you can use CommonJS syntax:
// require('./commands')

// Manejar excepciones no capturadas de jQuery
// Esto previene que los tests fallen por errores de jQuery en la aplicación
Cypress.on('uncaught:exception', (err, runnable) => {
  // Ignorar errores de jQuery relacionados con selectores y caracteres especiales
  if (err.message.includes('Syntax error, unrecognized expression') || 
      err.message.includes('tcla-@') ||
      err.message.includes('tcla-!') ||
      err.message.includes('tcla-#') ||
      err.message.includes('tcla-$') ||
      err.message.includes('tcla-.')) {
    return false // No fallar el test por este error
  }
  // Para otros errores, permitir que Cypress los maneje normalmente
  return true
})

// Hide fetch/XHR requests from command log
Cypress.on('fail', (error, runnable) => {
  // we now have access to the err instance
  // and the mocha runnable this failed on
  throw error // throw error to have test still fail
})
