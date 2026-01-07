/// <reference types="cypress" />

describe('Inventario - Productos', () => {
  // Usar credenciales del archivo de configuración
  const testEmail = Cypress.env('TEST_EMAIL') || 'test@example.com'
  const testPassword = Cypress.env('TEST_PASSWORD') || 'password123'

  beforeEach(() => {
    // Limpiar localStorage y cookies antes de cada test
    cy.clearLocalStorage()
    cy.clearCookies()
    cy.window().then((win) => {
      win.sessionStorage.clear()
    })
  })

  it('debe iniciar sesión y navegar a la página de productos', () => {
    // Hacer login primero
    cy.login(testEmail, testPassword)
    
    // Navegar a la página de productos
    cy.visit('/productos')
    
    // Verificar que la página se cargó correctamente
    cy.contains('h2', 'Inventario').should('be.visible')
    
    // Verificar que existe la tabla de productos o mensaje de "no tiene productos"
    cy.get('body').then(($body) => {
      if ($body.find('table').length > 0) {
        // Si hay tabla, verificar que tenga las columnas correctas
        cy.contains('th', 'Producto').should('be.visible')
        cy.contains('th', 'Estado').should('be.visible')
        cy.contains('th', 'Categoría').should('be.visible')
        cy.contains('th', 'Stock').should('be.visible')
      } else {
        // Si no hay tabla, verificar mensaje de "no tiene productos"
        cy.contains('No tiene productos registrados').should('be.visible')
      }
    })
  })

  it('debe mostrar correctamente los elementos de la página de productos', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Verificar elementos principales de la página
    cy.contains('h2', 'Inventario').should('be.visible')
    
    // Verificar botones principales
    cy.contains('button', 'Añadir producto').should('be.visible')
    
    // Verificar controles de búsqueda y filtros
    cy.get('input[name="buscador"]').should('be.visible')
    // El botón de filtrar tiene un identificador único data-cy
    cy.get('button[data-cy="btn-filtrar-productos"]').should('be.visible')
    // El botón de descargar tiene clase tcla-F6 y tooltip
    cy.get('button.tcla-F6').should('be.visible')
    
    // Verificar selectores de categoría y bodega
    cy.get('select[name="id_bodega"]').should('be.visible')
    cy.get('ng-select[name="id_categoria"]').should('be.visible')
    
    // Verificar botones de navegación
    cy.contains('button', 'Todos').should('be.visible')
    cy.contains('button', 'Ajustes').should('be.visible')
    cy.contains('button', 'Traslados').should('be.visible')
  })

  it('debe buscar productos', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Buscar un producto (usar un término genérico)
    cy.get('input[name="buscador"]').clear().type('test', { delay: 0 })
    
    // Presionar Enter o esperar a que se filtre automáticamente
    cy.get('input[name="buscador"]').type('{enter}')
    
    // Esperar a que se complete la búsqueda
    cy.wait(2000)
    
    // Verificar que la URL tenga el parámetro de búsqueda
    cy.url().should('include', 'buscador=test')
  })

  it('debe filtrar productos por categoría', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que se carguen las categorías
    cy.wait(2000)
    
    // Verificar que exista el selector de categoría
    cy.get('ng-select[name="id_categoria"]').should('be.visible')
    
    // Intentar seleccionar una categoría si hay disponibles
    cy.get('ng-select[name="id_categoria"]').then(($select) => {
      if ($select.find('ng-option').length > 1) {
        // Hay categorías disponibles, seleccionar la primera (después de "Categoria")
        cy.get('ng-select[name="id_categoria"]').click()
        cy.wait(500)
        // Seleccionar la primera opción que no sea "Categoria"
        cy.get('ng-option').not(':contains("Categoria")').first().click()
        cy.wait(2000)
        
        // Verificar que se aplicó el filtro
        cy.url().should('include', 'id_categoria')
      }
    })
  })

  it('debe filtrar productos por bodega', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que se carguen las bodegas
    cy.wait(2000)
    
    // Verificar que exista el selector de bodega
    cy.get('select[name="id_bodega"]').should('be.visible')
    
    // Intentar seleccionar una bodega si hay disponibles
    cy.get('select[name="id_bodega"] option').then(($options) => {
      if ($options.length > 1) {
        // Hay bodegas disponibles, seleccionar la primera (después de "Bodega")
        cy.get('select[name="id_bodega"]').select(1)
        cy.wait(2000)
        
        // Verificar que se aplicó el filtro
        cy.url().should('include', 'id_bodega')
      }
    })
  })

  it('debe abrir el modal de filtros y aplicar filtros', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Hacer clic en el botón de filtrar usando el identificador único
    cy.get('button[data-cy="btn-filtrar-productos"]').click()
    
    // Esperar a que se carguen los proveedores
    cy.wait(2000)
    
    // Verificar que el modal se abrió
    cy.contains('h2', 'Filtrar').should('be.visible')
    
    // Verificar que los campos del filtro estén presentes
    cy.get('select[name="filtros.estado"]').should('be.visible')
    cy.get('select[name="filtros.id_categoria"]').should('be.visible')
    cy.get('select[name="id_bodega"]').should('be.visible')
    
    // Aplicar un filtro de estado
    cy.get('select[name="filtros.estado"]').select('1') // Activos
    
    // Esperar a que se carguen los proveedores
    cy.wait(1000)
    
    // Aplicar el filtro
    cy.contains('button', 'Filtrar').click()
    
    // Esperar a que se cierre el modal y se apliquen los filtros
    cy.wait(2000)
    
    // Verificar que la URL tenga el parámetro de estado
    cy.url().should('include', 'estado=1')
  })

  it('debe ordenar productos por diferentes columnas', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Verificar que hay productos (o al menos la tabla)
    cy.get('table').should('exist')
    
    // Ordenar por nombre (ascendente)
    cy.contains('th', 'Producto').click()
    cy.wait(2000)
    cy.url().should('include', 'orden=nombre')
    cy.url().should('include', 'direccion=asc')
    
    // Ordenar por nombre (descendente) - segundo clic
    cy.contains('th', 'Producto').click()
    cy.wait(2000)
    cy.url().should('include', 'direccion=desc')
    
    // Ordenar por precio
    cy.contains('th', 'Precio sin IVA').click()
    cy.wait(2000)
    cy.url().should('include', 'orden=precio')
  })

  it('debe cambiar el estado del producto (activo/inactivo)', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Interceptar las peticiones antes de hacer cualquier cambio
    cy.intercept('POST', '**/api/producto').as('updateProducto')
    cy.intercept('GET', '**/api/productos**').as('reloadProductos')
    
    // Verificar que hay productos y obtener el select
    cy.get('body').then(($body) => {
      const $rows = $body.find('tbody tr')
      if ($rows.length > 0) {
        // Hay productos, obtener el valor actual del select usando jQuery
        const $firstRow = $rows.first()
        const $select = $firstRow.find('select[name="enable"]')
        
        if ($select.length > 0 && $select.is(':visible')) {
          // Obtener el valor actual usando jQuery
          const currentValue = $select.val() as string
          const newValue = currentValue === '1' ? '0' : '1'
          
          // Cambiar el estado usando Cypress (más confiable para disparar eventos de Angular)
          // Esperar explícitamente a que el select exista antes de cambiarlo
          cy.get('tbody tr').should('have.length.greaterThan', 0)
          cy.get('tbody tr').first().within(() => {
            cy.get('select[name="enable"]').should('be.visible').select(newValue)
          })
          
          // Esperar a que se complete la petición de actualización
          cy.wait('@updateProducto', { timeout: 10000 }).then((interception) => {
            // Verificar que la petición fue exitosa
            expect(interception.response?.statusCode).to.eq(200)
            // Verificar que el estado se guardó correctamente en el backend
            if (interception.response?.body?.producto) {
              expect(interception.response.body.producto.enable).to.eq(parseInt(newValue))
            }
          })
          
          // Esperar a que Angular recargue la lista automáticamente
          cy.wait('@reloadProductos', { timeout: 10000 })
          
          // El test pasa si la petición fue exitosa
          // No verificamos el DOM porque puede cambiar (producto puede desaparecer si cambió a inactivo)
          cy.log(`Estado cambiado de ${currentValue} a ${newValue} - petición exitosa verificada`)
        } else {
          cy.log('Select de estado no visible o no encontrado (puede estar oculto en móvil)')
        }
      } else {
        cy.log('No hay productos en la tabla para cambiar el estado')
      }
    })
  })

  it('debe abrir el modal de descarga', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Hacer clic en el botón de descargar (usar clase tcla-F6)
    cy.get('button.tcla-F6').click()
    
    // Esperar a que se abra el modal
    cy.wait(1000)
    
    // Verificar que el modal se abrió
    cy.contains('h4', 'Descargar registro de inventario').should('be.visible')
    
    // Verificar opciones de descarga
    cy.contains('button', 'Descargar productos').should('be.visible')
    cy.contains('label', 'Descargar detalle de inventario').should('be.visible')
    cy.contains('label', 'Descargar Kardex').should('be.visible')
    
    // Cerrar el modal
    cy.get('.modal-header .btn-close').click()
    cy.wait(500)
  })

  it('debe navegar a la página de crear producto', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Verificar que el botón existe y tiene permisos
    cy.get('button').contains('Añadir producto').then(($btn) => {
      if (!$btn.is(':disabled')) {
        // Hacer clic en el botón
        cy.contains('button', 'Añadir producto').click()
        
        // Verificar que navegó a la página de crear producto
        cy.url({ timeout: 10000 }).should('include', '/producto/crear')
      }
    })
  })

  it('debe cambiar el tamaño de paginación', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Verificar que existe el selector de paginación
    cy.get('select[name="paginate"]').should('be.visible')
    
    // Cambiar el tamaño de paginación a 25
    cy.get('select[name="paginate"]').select('25')
    cy.wait(2000)
    
    // Verificar que la URL tiene el parámetro de paginación
    cy.url().should('include', 'paginate=25')
  })

  it('debe navegar a diferentes secciones de inventario', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Verificar botones de navegación
    cy.contains('button', 'Todos').should('be.visible')
    cy.contains('button', 'Ajustes').should('be.visible')
    cy.contains('button', 'Traslados').should('be.visible')
    cy.contains('button', 'Consignas').should('be.visible')
    cy.contains('button', 'Otras Entradas').should('be.visible')
    cy.contains('button', 'Otras Salidas').should('be.visible')
    
    // Navegar a Ajustes
    cy.contains('button', 'Ajustes').click()
    cy.url({ timeout: 10000 }).should('include', '/ajustes')
    
    // Volver a productos
    cy.visit('/productos')
    cy.wait(1000)
    
    // Navegar a Traslados
    cy.contains('button', 'Traslados').click()
    cy.url({ timeout: 10000 }).should('include', '/traslados')
  })

  it('debe mostrar correctamente la información de stock', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Verificar que la columna de stock existe
    cy.contains('th', 'Stock').should('be.visible')
    
    // Si hay productos, verificar que muestren stock
    cy.get('tbody tr').then(($rows) => {
      if ($rows.length > 0) {
        // Verificar que al menos una fila tiene información de stock
        cy.get('tbody tr').first().within(() => {
          // El stock puede ser un número o "Sin control"
          // contains() no acepta regex, así que verificamos el texto directamente
          cy.get('td.text-center').should(($td) => {
            const text = $td.text().trim()
            // Verificar que sea un número o "Sin control"
            const esValido = text === 'Sin control' || !isNaN(parseInt(text))
            expect(esValido, `El stock debe ser un número o "Sin control", pero es: "${text}"`).to.be.true
          })
        })
      }
    })
  })

  it('debe filtrar productos con stock bajo', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Abrir modal de filtros usando el identificador único
    cy.get('button[data-cy="btn-filtrar-productos"]').click()
    cy.wait(2000) // Esperar a que se carguen los proveedores
    
    // Activar el filtro de stock bajo
    cy.get('input[name="sin_stock"]').check()
    
    // Aplicar filtro (el botón dentro del modal)
    cy.get('.modal-body').contains('button', 'Filtrar').click()
    cy.wait(2000)
    
    // Verificar que se aplicó el filtro
    cy.url().should('include', 'sin_stock')
  })

  it('debe filtrar productos compuestos', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Esperar a que la página cargue
    cy.wait(2000)
    
    // Abrir modal de filtros usando el identificador único
    cy.get('button[data-cy="btn-filtrar-productos"]').click()
    cy.wait(2000) // Esperar a que se carguen los proveedores
    
    // Activar el filtro de productos compuestos
    cy.get('input[name="compuestos"]').check()
    
    // Aplicar filtro (el botón dentro del modal)
    cy.get('.modal-body').contains('button', 'Filtrar').click()
    cy.wait(2000)
    
    // Verificar que se aplicó el filtro
    cy.url().should('include', 'compuestos')
  })

  it('debe resetear los filtros con el botón "Todos"', () => {
    cy.login(testEmail, testPassword)
    cy.visit('/productos')
    
    // Aplicar algunos filtros primero
    cy.wait(2000)
    cy.get('select[name="id_bodega"]').then(($select) => {
      if ($select.find('option').length > 1) {
        cy.get('select[name="id_bodega"]').select(1)
        cy.wait(2000)
        cy.url().should('include', 'id_bodega')
      }
    })
    
    // Hacer clic en "Todos" para resetear filtros
    cy.contains('button', 'Todos').click()
    cy.wait(2000)
    
    // Verificar que los filtros se resetearon
    // Nota: El botón "Todos" puede dejar id_bodega= vacío en lugar de eliminarlo
    // Verificamos que no tenga un valor específico
    cy.url().then((url) => {
      // Verificar que id_bodega no tenga un valor numérico (solo puede estar vacío)
      expect(url).to.not.match(/id_bodega=\d+/)
    })
  })
})
