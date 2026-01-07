/// <reference types="cypress" />

describe('Registro de Nueva Empresa - Pagar Después', () => {
  beforeEach(() => {
    // Limpiar localStorage y cookies antes de cada test
    cy.clearLocalStorage()
    cy.clearCookies()
    cy.window().then((win) => {
      win.sessionStorage.clear()
    })
    
    // Interceptar y manejar errores de la API de países (opcional)
    cy.intercept('GET', 'https://restcountries.com/v3.1/all?order=name', {
      statusCode: 200,
      body: []
    }).as('getCountries')
  })

  it('should display registration page correctly', () => {
    cy.visit('/registro')
    
    // Verificar que los elementos principales estén presentes
    cy.contains('h2', 'Registro').should('be.visible')
    cy.contains('p', '¡Bienvenido a SmartPyme!').should('be.visible')
    
    // Verificar campos del formulario
    cy.get('input[name="empresa"]').should('be.visible')
    cy.get('input[name="name"]').should('be.visible')
    cy.get('select[name="industria"]').should('be.visible')
    cy.get('select[name="frecuencia_pago"]').should('be.visible')
    cy.get('select[name="plan"]').should('be.visible')
    cy.get('input[name="correo"]').should('be.visible')
    cy.get('input[name="telefono"]').should('be.visible')
    cy.get('input[name="password"]').should('be.visible')
    cy.get('button[type="submit"]').should('be.visible').and('contain', 'Registrarme')
    
    // Verificar enlace a login
    cy.contains('a', 'Inicia sesión').should('be.visible')
  })

  it('should show validation errors when submitting empty form', () => {
    cy.visit('/registro')
    
    // Intentar enviar el formulario sin datos
    cy.get('button[type="submit"]').click()
    
    // Verificar que los campos requeridos muestren error
    cy.get('input[name="empresa"]:invalid').should('exist')
    cy.get('input[name="name"]:invalid').should('exist')
    cy.get('input[name="correo"]:invalid').should('exist')
    cy.get('input[name="telefono"]:invalid').should('exist')
    cy.get('input[name="password"]:invalid').should('exist')
  })

  it('should successfully register a new company and navigate to payment page', () => {
    // Generar datos únicos para evitar conflictos
    const timestamp = Date.now()
    const testData = {
      empresa: `Empresa Test ${timestamp}`,
      name: `Usuario Test ${timestamp}`,
      email: `test${timestamp}@example.com`,
      telefono: '1234-5678',
      password: 'Test1234!@#$',
      industria: 'Comercio',
      frecuencia_pago: 'Mensual',
      plan: '2', // Estándar - esto establece user_limit=2, sucursal_limit=1
      pais: 'El Salvador'
    }

    cy.visit('/registro')
    
    // Esperar a que la página cargue completamente
    cy.contains('h2', 'Registro').should('be.visible')
    
    // Esperar a que se carguen los países (puede fallar pero no es crítico)
    cy.wait(2000)
    
    // Interceptar la petición de registro ANTES de llenar el formulario
    cy.intercept('POST', '**/api/register').as('registerRequest')
    
    // Llenar el formulario de registro
    cy.get('input[name="empresa"]').clear().type(testData.empresa, { delay: 0 })
    cy.get('input[name="name"]').clear().type(testData.name, { delay: 0 })
    cy.get('select[name="industria"]').select(testData.industria)
    
    // Seleccionar tipo de plan
    cy.get('select[name="frecuencia_pago"]').select(testData.frecuencia_pago)
    
    // Esperar a que Angular procese el cambio y actualice el precio
    cy.wait(1000)
    
    // Seleccionar plan - esto dispara setPlan() que establece user_limit y sucursal_limit
    cy.get('select[name="plan"]').select(testData.plan)
    
    // Esperar a que Angular procese el cambio y establezca los límites
    // El método setPlan() se ejecuta cuando cambia el plan
    cy.wait(1000)
    
    // Verificar que el precio se haya actualizado (indica que setPlan() se ejecutó)
    cy.contains('Precio:').should('be.visible')
    
    // Llenar correo - usar force para evitar problemas con jQuery
    cy.get('input[name="correo"]').clear().type(testData.email, { delay: 0, force: true })
    
    // Seleccionar país y llenar teléfono
    cy.get('select[name="pais"]').select(testData.pais)
    cy.get('input[name="telefono"]').clear().type(testData.telefono, { delay: 0 })
    
    // Llenar contraseña - usar force para evitar problemas con jQuery
    cy.get('input[name="password"]').clear().type(testData.password, { delay: 0, force: true })
    
    // Enviar el formulario
    cy.get('button[type="submit"]').click()
    
    // Verificar que el botón muestre "Registrando..." mientras carga
    cy.get('button[type="submit"]').should('contain', 'Registrando...')
    
    // Esperar a que se complete la petición de registro
    cy.wait('@registerRequest', { timeout: 20000 }).then((interception) => {
      // Log del estado de la respuesta
      cy.log(`Status: ${interception.response?.statusCode}`)
      
      // Si hay un error, mostrar información útil
      if (interception.response?.statusCode !== 200) {
        const errorBody = interception.response?.body
        cy.log('Error en registro:', JSON.stringify(errorBody))
        // Si el error es 400, puede ser un problema de validación
        if (interception.response?.statusCode === 400) {
          cy.log('Error 400: Verifica que todos los campos requeridos estén presentes y sean válidos')
        }
        // No fallar el test aquí, continuar para ver qué pasa
      }
    })
    
    // Esperar a que se complete el registro y navegar a la página de pago
    // Dar más tiempo si el registro fue exitoso
    cy.url({ timeout: 20000 }).then((url) => {
      if (url.includes('/pago')) {
        // Registro exitoso - verificar página de pago
        cy.contains('Plan').should('be.visible')
        cy.contains('Total').should('be.visible')
      } else {
        // Si falló, verificar que se muestre un mensaje de error
        cy.log('El registro no navegó a /pago, URL actual:', url)
        // Verificar si hay mensajes de error visibles
        cy.get('body').then(($body) => {
          if ($body.find('.alert-danger, .error, [role="alert"]').length > 0) {
            cy.log('Se encontraron mensajes de error en la página')
          }
        })
        // Marcar el test como fallido solo si realmente no se registró
        cy.url().should('include', '/pago')
      }
    })
  })

  it('should complete registration and select "Pagar después" option', () => {
    // Generar datos únicos para evitar conflictos
    const timestamp = Date.now()
    const testData = {
      empresa: `Empresa Test ${timestamp}`,
      name: `Usuario Test ${timestamp}`,
      email: `test${timestamp}@example.com`,
      telefono: '1234-5678',
      password: 'Test1234!@#$',
      industria: 'Servicio',
      frecuencia_pago: 'Mensual',
      plan: '2', // Estándar - esto establece user_limit=2, sucursal_limit=1
      pais: 'El Salvador'
    }

    cy.visit('/registro')
    
    // Esperar a que la página cargue completamente
    cy.contains('h2', 'Registro').should('be.visible')
    
    // Esperar a que se carguen los países (puede fallar pero no es crítico)
    cy.wait(2000)
    
    // Llenar el formulario de registro
    cy.get('input[name="empresa"]').clear().type(testData.empresa, { delay: 0 })
    cy.get('input[name="name"]').clear().type(testData.name, { delay: 0 })
    cy.get('select[name="industria"]').select(testData.industria)
    
    // Seleccionar tipo de plan
    cy.get('select[name="frecuencia_pago"]').select(testData.frecuencia_pago)
    
    // Esperar a que Angular procese el cambio
    cy.wait(1000)
    
    // Seleccionar plan - esto dispara setPlan() que establece user_limit y sucursal_limit
    cy.get('select[name="plan"]').select(testData.plan)
    
    // Esperar a que Angular procese el cambio y establezca los límites
    cy.wait(1000)
    
    // Verificar que el precio se haya actualizado (indica que setPlan() se ejecutó)
    cy.contains('Precio:').should('be.visible')
    
    // Llenar correo - usar force para evitar problemas con jQuery
    cy.get('input[name="correo"]').clear().type(testData.email, { delay: 0, force: true })
    
    // Seleccionar país y llenar teléfono
    cy.get('select[name="pais"]').select(testData.pais)
    cy.get('input[name="telefono"]').clear().type(testData.telefono, { delay: 0 })
    
    // Llenar contraseña - usar force para evitar problemas con jQuery
    cy.get('input[name="password"]').clear().type(testData.password, { delay: 0, force: true })
    
    // Interceptar la petición de registro
    cy.intercept('POST', '**/api/register').as('registerRequest')
    
    // Enviar el formulario
    cy.get('button[type="submit"]').click()
    
    // Verificar que el botón muestre "Registrando..." mientras carga
    cy.get('button[type="submit"]').should('contain', 'Registrando...')
    
    // Esperar a que se complete la petición de registro
    cy.wait('@registerRequest', { timeout: 20000 }).then((interception) => {
      // Log del estado de la respuesta
      cy.log(`Status: ${interception.response?.statusCode}`)
      
      // Si hay un error, mostrar información útil
      if (interception.response?.statusCode !== 200) {
        const errorBody = interception.response?.body
        cy.log('Error en registro:', JSON.stringify(errorBody))
        // Si el error es 400, puede ser un problema de validación
        if (interception.response?.statusCode === 400) {
          cy.log('Error 400: Verifica que todos los campos requeridos estén presentes y sean válidos')
        }
        // Fallar el test si el registro no fue exitoso
        throw new Error(`Registro falló con status ${interception.response?.statusCode}`)
      }
    })
    
    // Esperar a que se complete el registro y navegar a la página de pago
    cy.url({ timeout: 20000 }).should('include', '/pago')
    
    // Verificar que la página de pago se muestre correctamente
    cy.contains('Plan').should('be.visible')
    cy.contains('Puedes pagar en este momento o después de 3 días de prueba').should('be.visible')
    
    // Verificar que existan los botones de pago
    cy.contains('button', 'Pagar con tarjeta').should('be.visible')
    // El botón "Pagar después" puede ser un button o un link
    cy.get('button.btn-link, a.btn-link').contains('Pagar después').should('be.visible')
    
    // Hacer clic en "Pagar después"
    cy.get('button.btn-link, a.btn-link').contains('Pagar después').click()
    
    // Esperar a que se procese la acción
    cy.wait(3000)
    
    // Verificar que se haya redirigido (probablemente al home o login)
    // El método backToHome() navega a '/'
    cy.url({ timeout: 10000 }).should('not.include', '/pago')
  })

  it('should validate password requirements', () => {
    cy.visit('/registro')
    
    // Intentar con contraseña débil
    cy.get('input[name="password"]').clear().type('weak', { delay: 0 })
    cy.get('input[name="password"]').blur()
    
    // Verificar que se muestre el mensaje de requisitos de contraseña
    cy.contains('La contraseña debe tener entre 8 y 16 caracteres').should('be.visible')
  })

  it('should allow selecting different plan types', () => {
    cy.visit('/registro')
    
    // Llenar datos básicos primero
    cy.get('input[name="empresa"]').clear().type('Test Empresa', { delay: 0 })
    cy.get('input[name="name"]').clear().type('Test Usuario', { delay: 0 })
    cy.get('select[name="industria"]').select('Comercio')
    
    // Probar diferentes tipos de plan
    const tiposPlan = ['Mensual', 'Trimestral', 'Anual']
    
    tiposPlan.forEach((tipo) => {
      cy.get('select[name="frecuencia_pago"]').select(tipo)
      cy.wait(500)
      
      // Verificar que el select tenga el valor seleccionado
      cy.get('select[name="frecuencia_pago"]').should('have.value', tipo)
    })
  })

  it('should allow selecting different plans', () => {
    cy.visit('/registro')
    
    // Llenar datos básicos
    cy.get('input[name="empresa"]').clear().type('Test Empresa', { delay: 0 })
    cy.get('input[name="name"]').clear().type('Test Usuario', { delay: 0 })
    cy.get('select[name="industria"]').select('Comercio')
    cy.get('select[name="frecuencia_pago"]').select('Mensual')
    cy.wait(500)
    
    // Probar diferentes planes
    const planes = ['2', '3', '4'] // Estándar, Avanzado, Pro
    
    planes.forEach((plan) => {
      cy.get('select[name="plan"]').select(plan)
      cy.wait(500)
      
      // Verificar que el select tenga el valor seleccionado
      cy.get('select[name="plan"]').should('have.value', plan)
      
      // Verificar que se muestre el precio (si aplica)
      // Esto puede variar según la implementación
    })
  })

  it('should navigate to login page from registration', () => {
    cy.visit('/registro')
    
    cy.contains('a', 'Inicia sesión').click()
    
    // Verificar que se navegó a la página de login
    cy.url().should('include', '/login')
    cy.contains('h2', 'Inicio de sesión').should('be.visible')
  })

  it('should show price when plan is selected', () => {
    cy.visit('/registro')
    
    // Llenar datos básicos
    cy.get('input[name="empresa"]').clear().type('Test Empresa', { delay: 0 })
    cy.get('input[name="name"]').clear().type('Test Usuario', { delay: 0 })
    cy.get('select[name="industria"]').select('Comercio')
    cy.get('select[name="frecuencia_pago"]').select('Mensual')
    cy.wait(500)
    cy.get('select[name="plan"]').select('2') // Estándar
    cy.wait(1000)
    
    // Verificar que se muestre el precio
    cy.contains('Precio:').should('be.visible')
  })
})
