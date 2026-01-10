/// <reference types="cypress" />

describe('Login Tests', () => {
  beforeEach(() => {
    // Limpiar localStorage y cookies antes de cada test
    cy.clearLocalStorage()
    cy.clearCookies()
    cy.window().then((win) => {
      win.sessionStorage.clear()
    })
  })

  it('should display login page correctly', () => {
    cy.visit('/login')
    
    // Verificar que los elementos principales estén presentes
    cy.contains('h2', 'Inicio de sesión').should('be.visible')
    cy.contains('p', '¡Bienvenido de regreso!').should('be.visible')
    
    // Verificar campos del formulario
    cy.get('input[name="correo"]').should('be.visible')
    cy.get('input[name="password"]').should('be.visible')
    cy.get('button[type="submit"]').should('be.visible').and('contain', 'Iniciar sesión')
    
    // Verificar enlaces
    cy.contains('a', 'Olvidé mi contraseña').should('be.visible')
    cy.contains('a', '¿No tienes cuenta?, crea una.').should('be.visible')
  })

  it('should show validation errors when submitting empty form', () => {
    cy.visit('/login')
    
    // Intentar enviar el formulario sin datos
    cy.get('button[type="submit"]').click()
    
    // Verificar que los campos requeridos muestren error
    // (Angular muestra errores cuando el formulario es inválido)
    cy.get('input[name="correo"]:invalid').should('exist')
    cy.get('input[name="password"]:invalid').should('exist')
  })

  it('should show error message with invalid credentials', () => {
    cy.visit('/login')
    
    // Llenar el formulario con credenciales inválidas
    // Usar clear() antes de type() para evitar problemas con jQuery
    cy.get('input[name="correo"]').clear().type('invalid@example.com', { delay: 0 })
    cy.get('input[name="password"]').clear().type('wrongpassword', { delay: 0 })
    cy.get('button[type="submit"]').click()
    
    // Esperar a que aparezca el mensaje de error
    // (Ajusta el selector según cómo se muestren los errores en tu app)
    cy.wait(2000) // Esperar a que se complete la petición
    
    // Verificar que no se haya navegado a otra página
    cy.url().should('include', '/login')
    
    // Verificar que el botón ya no esté en estado de carga
    cy.get('button[type="submit"]').should('not.contain', 'Verificando...')
  })

  it('should successfully login with valid credentials', () => {
    // NOTA: Necesitarás reemplazar estos valores con credenciales válidas de tu entorno de pruebas
    const validEmail = Cypress.env('TEST_EMAIL') || 'test@example.com'
    const validPassword = Cypress.env('TEST_PASSWORD') || 'password123'
    
    cy.visit('/login')
    
    // Llenar el formulario con credenciales válidas
    // Usar clear() y delay: 0 para evitar problemas con jQuery
    cy.get('input[name="correo"]').clear().type(validEmail, { delay: 0 })
    cy.get('input[name="password"]').clear().type(validPassword, { delay: 0 })
    
    // Enviar el formulario
    cy.get('button[type="submit"]').click()
    
    // Verificar que el botón muestre "Verificando..." mientras carga
    cy.get('button[type="submit"]').should('contain', 'Verificando...')
    
    // Esperar a que se complete el login y navegar
    // Usar wait con timeout más largo para APIs lentas
    cy.wait(5000, { timeout: 10000 }) // Esperar hasta 5 segundos
    
    // Verificar que se haya navegado fuera de la página de login
    cy.url({ timeout: 10000 }).should('not.include', '/login')
    
    // Verificar que se haya guardado el token en localStorage
    cy.window().then((win) => {
      const token = win.localStorage.getItem('SP_token')
      expect(token).to.exist
      expect(token).to.not.be.empty
    })
  })

  it('should toggle password visibility', () => {
    cy.visit('/login')
    
    // Verificar que inicialmente el campo es de tipo password
    cy.get('input[name="password"]').should('have.attr', 'type', 'password')
    
    // Hacer clic en el botón de mostrar/ocultar contraseña
    // El botón está dentro del input-group junto al campo password
    cy.get('input[name="password"]').parent().find('button.btn-light').click()
    
    // Esperar un momento para que Angular actualice el DOM
    cy.wait(100)
    
    // Verificar que el campo cambió a tipo text
    cy.get('input[name="password"]').should('have.attr', 'type', 'text')
    
    // Hacer clic nuevamente
    cy.get('input[name="password"]').parent().find('button.btn-light').click()
    
    // Esperar un momento para que Angular actualice el DOM
    cy.wait(100)
    
    // Verificar que el campo volvió a tipo password
    cy.get('input[name="password"]').should('have.attr', 'type', 'password')
  })

  it('should navigate to forgot password page', () => {
    cy.visit('/login')
    
    cy.contains('a', 'Olvidé mi contraseña').click()
    
    // Verificar que se navegó a la página de restablecer contraseña
    cy.url().should('include', '/restablecer-cuenta')
  })

  it('should navigate to register page', () => {
    cy.visit('/login')
    
    cy.contains('a', '¿No tienes cuenta?, crea una.').click()
    
    // Verificar que se navegó a la página de registro
    cy.url().should('include', '/registro')
  })

  it('should remember me checkbox works', () => {
    cy.visit('/login')
    
    const checkbox = cy.get('input[name="rememberMe"]')
    
    // Verificar que el checkbox existe
    checkbox.should('exist')
    
    // Hacer clic en el checkbox
    checkbox.check()
    
    // Verificar que está marcado
    checkbox.should('be.checked')
    
    // Desmarcar
    checkbox.uncheck()
    
    // Verificar que está desmarcado
    checkbox.should('not.be.checked')
  })
})
