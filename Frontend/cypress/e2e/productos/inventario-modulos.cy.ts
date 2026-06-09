/// <reference types="cypress" />
import {
  assertPaginaLotes,
  buscarOperacionInventario,
  buscarProductoKardex,
  inventarioSession,
  INVENTARIO_OPERACION_TABS,
  modalVisible,
} from '../../support/inventario'

describe('Inventario - Operaciones (ajustes, traslados, entradas, salidas)', () => {
  beforeEach(() => {
    inventarioSession()
  })

  it('carga listado de ajustes con filtros y acciones', () => {
    cy.visit('/ajustes')
    cy.contains('h2', 'Inventario').should('be.visible')
    cy.get('section.bg-white input[name="search"]').first().should('be.visible')
    cy.get('button[data-cy="btn-filtrar-ajustes"]').should('be.visible')
    cy.get('select[name="estado"]').should('be.visible')
    cy.get('select[name="id_bodega"]').should('be.visible')
    cy.contains('table thead th', 'Producto').should('be.visible')
    cy.contains('table thead th', 'Ajuste').should('be.visible')

    INVENTARIO_OPERACION_TABS.forEach((tab) => {
      cy.contains('button', tab).should('be.visible')
    })
  })

  it('busca y filtra ajustes', () => {
    cy.visit('/ajustes')
    buscarOperacionInventario('ajuste')
    cy.url({ timeout: 10000 }).should('include', 'search=ajuste')

    cy.get('button[data-cy="btn-filtrar-ajustes"]').click()
    cy.contains('h2', 'Filtros').should('be.visible')
    modalVisible().find('.btn-close').click()
  })

  it('carga listado de traslados con filtros', () => {
    cy.visit('/traslados')
    cy.contains('h2', 'Inventario').should('be.visible')
    cy.get('section.bg-white input[name="search"]').first().should('be.visible')
    cy.get('select[name="estado"]').should('be.visible')
    cy.get('select[name="id_bodega_de"]').should('be.visible')
    cy.contains('table thead th', 'Producto').should('be.visible')

    cy.get('body').then(($body) => {
      if ($body.text().includes('Añadir traslado')) {
        cy.contains('button', 'Añadir traslado').should('be.visible')
      }
    })
  })

  it('busca traslados por texto', () => {
    cy.visit('/traslados')
    buscarOperacionInventario('traslado')
    cy.url({ timeout: 10000 }).should('include', 'search=traslado')
  })

  it('carga listado de otras entradas', () => {
    cy.visit('/entradas')
    cy.contains('h2', 'Entradas de Inventario').should('be.visible')
    cy.get('input[name="buscador"]').first().should('be.visible')
    cy.get('ng-select[name="estado"]').should('be.visible')
    cy.get('ng-select[name="tipo"]').should('be.visible')
    cy.contains('table thead th', 'Concepto').should('be.visible')
    cy.contains('table thead th', 'Bodega').should('be.visible')

    cy.get('body').then(($body) => {
      if ($body.text().includes('Añadir entrada')) {
        cy.contains('button', 'Añadir entrada').should('be.visible')
      }
    })
  })

  it('filtra otras entradas por estado', () => {
    cy.visit('/entradas')
    cy.get('ng-select[name="estado"]').click()
    cy.get('.ng-dropdown-panel .ng-option').contains('Aprobada').click({ force: true })
    cy.url({ timeout: 10000 }).should('include', 'estado')
  })

  it('carga listado de otras salidas', () => {
    cy.visit('/salidas')
    cy.contains('h2', 'Salidas de Inventario').should('be.visible')
    cy.get('input[name="buscador"]').first().should('be.visible')
    cy.get('ng-select[name="estado"]').should('be.visible')
    cy.get('ng-select[name="tipo"]').should('be.visible')
    cy.contains('table thead th', 'Concepto').should('be.visible')

    cy.get('body').then(($body) => {
      if ($body.text().includes('Añadir salida')) {
        cy.contains('button', 'Añadir salida').should('be.visible')
      }
    })
  })

  it('navega entre todas las operaciones de inventario desde productos', () => {
    cy.visit('/productos')
    cy.contains('h2', 'Inventario').should('be.visible')

    const rutas: Record<string, string> = {
      Ajustes: '/ajustes',
      Traslados: '/traslados',
      'Otras Entradas': '/entradas',
      'Otras Salidas': '/salidas',
    }

    Object.entries(rutas).forEach(([tab, path]) => {
      cy.visit('/productos')
      cy.contains('button', tab).click()
      cy.url({ timeout: 10000 }).should('include', path)
    })
  })
})

describe('Inventario - Lotes', () => {
  beforeEach(() => {
    inventarioSession()
  })

  it('carga listado de lotes con estadísticas y filtros', () => {
    assertPaginaLotes(() => {
      cy.contains('h2', 'Lotes').should('be.visible')
      cy.get('input[name="numero_lote"]').should('be.visible')
      cy.contains('h6', 'Total').should('be.visible')
      cy.contains('h6', 'Sin Stock').should('be.visible')
      cy.get('select[name="id_bodega"]').should('be.visible')
      cy.contains('table thead th', 'Número de lote').should('be.visible')
      cy.contains('table thead th', 'Fecha vencimiento').should('be.visible')
    })
  })

  it('busca lotes por número', () => {
    assertPaginaLotes(() => {
      cy.get('input[name="numero_lote"]').type('LOTE', { delay: 0 })
      cy.get('input[name="numero_lote"]').type('{enter}')
      cy.url({ timeout: 10000 }).should('include', 'numero_lote')
    })
  })

  it('filtra lotes por tarjeta de estadística vencidos', () => {
    assertPaginaLotes(() => {
      cy.contains('h6', 'Vencidos').closest('.card').click()
      cy.url({ timeout: 10000 }).should('include', 'vencidos=true')
    })
  })
})

describe('Inventario - Kardex (UI actualizada)', () => {
  beforeEach(() => {
    inventarioSession()
  })

  it('muestra formulario completo de consulta', () => {
    cy.visit('/kardex')
    cy.contains('h2', 'Kardex').should('be.visible')
    cy.contains('small', 'Tarjeta de control de inventarios').should('be.visible')
    cy.get('ng-select[name="producto_kardex"]').should('be.visible')
    cy.get('select[name="id_inventario"]').should('be.visible')
    cy.get('select[name="detalle"]').should('be.visible')
    cy.contains('option', 'Ventas').should('exist')
    cy.contains('option', 'Traslados').should('exist')
    cy.get('input[name="filtros.inicio"]').should('be.visible')
    cy.get('input[name="filtros.fin"]').should('be.visible')
    cy.get('button.tcla-F6[type="submit"]').should('be.visible')
    cy.contains('p', 'TARJETA DE CONTROL DE INVENTARIOS').should('be.visible')
    cy.contains('th', 'ENTRADAS').should('be.visible')
    cy.contains('th', 'SALIDAS').should('be.visible')
    cy.contains('th', 'EXISTENCIAS').should('be.visible')
  })

  it('busca producto por typeahead y navega al kardex del producto', () => {
    cy.visit('/kardex')
    buscarProductoKardex('pr')

    cy.get('body').then(($body) => {
      if ($body.find('.ng-dropdown-panel .ng-option').length === 0) {
        buscarProductoKardex('00')
      }
    })

    cy.get('.ng-dropdown-panel .ng-option', { timeout: 10000 }).first().click({ force: true })
    cy.url({ timeout: 15000 }).should('match', /\/kardex\/\d+/)
  })

  it('filtra movimientos por tipo y rango de fechas', () => {
    cy.intercept('GET', '**/api/productos/kardex**').as('kardexData')

    cy.visit('/kardex')
    buscarProductoKardex('pr')
    cy.get('.ng-dropdown-panel .ng-option', { timeout: 10000 }).first().click({ force: true })
    cy.url({ timeout: 15000 }).should('match', /\/kardex\/\d+/)

    cy.get('select[name="detalle"]').select('Ajuste')
    cy.get('input[name="filtros.inicio"]').invoke('val').should('not.be.empty')
    cy.get('input[name="filtros.fin"]').invoke('val').should('not.be.empty')
    cy.get('button.tcla-F6[type="submit"]').click()
    cy.wait('@kardexData', { timeout: 15000 })

    cy.get('body').then(($body) => {
      const text = $body.text()
      const tieneMovimientos = $body.find('tbody tr td').length > 1
      const sinMovimientos = text.includes('No hay movimientos registrados')
      expect(tieneMovimientos || sinMovimientos).to.be.true
    })
  })

  it('muestra selector de lote cuando el producto usa inventario por lotes', () => {
    cy.visit('/kardex')
    buscarProductoKardex('lo')
    cy.get('body').then(($body) => {
      if ($body.find('.ng-dropdown-panel .ng-option').length === 0) {
        buscarProductoKardex('la')
      }
    })
    cy.get('.ng-dropdown-panel .ng-option', { timeout: 10000 }).first().click({ force: true })
    cy.url({ timeout: 15000 }).should('match', /\/kardex\/\d+/)

    cy.get('body').then(($body) => {
      if ($body.find('select[name="lote_id"]').length > 0) {
        cy.get('label').contains('Lote').should('be.visible')
        cy.get('select[name="lote_id"]').should('be.visible')
        cy.get('select[name="lote_id"] option').its('length').should('be.gte', 1)
        cy.contains('th', 'Lote').should('be.visible')
      } else {
        cy.log('Producto seleccionado sin inventario por lotes; selector omitido')
      }
    })
  })

  it('regresa a productos desde kardex', () => {
    cy.visit('/kardex')
    cy.contains('a, button', 'Regresar').click()
    cy.url({ timeout: 10000 }).should('include', '/productos')
  })
})

describe('Inventario - Categorías (cuenta contable)', () => {
  beforeEach(() => {
    inventarioSession()
  })

  it('carga listado con búsqueda y filtros', () => {
    cy.visit('/categorias')
    cy.contains('h2', 'Categorias').should('be.visible')
    cy.get('input[placeholder="Buscar categoria"]').should('be.visible')
    cy.get('select[name="filtros.estado"]').should('be.visible')
    cy.get('select[name="id_sucursal"]').should('be.visible')
    cy.contains('table thead th', 'Categoria').should('be.visible')
    cy.contains('button', 'Todos').should('be.visible')
  })

  it('busca categorías por nombre', () => {
    cy.intercept('GET', '**/api/categorias**').as('listarCategorias')
    cy.visit('/categorias')
    cy.get('input[placeholder="Buscar categoria"]').type('cat', { delay: 0 })
    cy.get('input[placeholder="Buscar categoria"]').type('{enter}')
    cy.wait('@listarCategorias', { timeout: 15000 })
      .its('request.url')
      .should('include', 'buscador=cat')
  })

  it('abre modal de edición con configuración contable en categorías existentes', () => {
    cy.visit('/categorias')
    cy.get('tbody tr', { timeout: 15000 }).then(($rows) => {
      if ($rows.length === 0 || $rows.text().includes('No tiene categorias')) {
        cy.log('Sin categorías para probar edición')
        return
      }

      cy.get('tbody tr').first().find('a.btn').click()
      modalVisible().within(() => {
        cy.contains('h2', 'Categoria').should('be.visible')
        cy.get('input[name="categoria.nombre"]').should('be.visible')
        cy.get('textarea[name="categoria.descripcion"]').should('be.visible')
        cy.get('input[name="categoria.enable"]').should('exist')
      })

      cy.get('body').then(($body) => {
        if ($body.text().includes('Configuración Contable')) {
          modalVisible().within(() => {
            cy.contains('h6', 'Configuración Contable').should('be.visible')
            cy.contains('a', 'Agregar sucursal').should('be.visible')
            cy.contains('th', 'Cuenta de inventario').should('be.visible')
            cy.contains('th', 'Cuenta de costos').should('be.visible')
            cy.contains('th', 'Cuenta de ingresos').should('be.visible')
            cy.contains('th', 'Cuenta de devoluciones').should('be.visible')
          })

          cy.contains('a', 'Agregar sucursal').click()
          modalVisible().within(() => {
            cy.contains('h2', 'Cuenta').should('be.visible')
            cy.get('select[name="id_sucursal"]').should('be.visible')
            cy.get('ng-select[name="cuenta.id_cuenta_contable_inventario"]').should('be.visible')
            cy.get('ng-select[name="cuenta.id_cuenta_contable_costo"]').should('be.visible')
            cy.get('ng-select[name="cuenta.id_cuenta_contable_ingresos"]').should('be.visible')
            cy.get('ng-select[name="cuenta.id_cuenta_contable_devoluciones"]').should('be.visible')
            cy.contains('button', 'Guardar').should('exist')
            cy.get('.btn-close').click()
          })
        } else {
          cy.log('Contabilidad no habilitada en esta cuenta demo')
        }
      })

      modalVisible().find('.btn-close').click()
    })
  })
})

describe('Inventario - Bodegas y servicios', () => {
  beforeEach(() => {
    inventarioSession()
  })

  it('carga la página de bodegas', () => {
    cy.visit('/bodegas')
    cy.contains('h2', 'Bodegas').should('be.visible')
    cy.get('input[name="buscador"]').first().should('be.visible')
    cy.contains('button', 'Todas').should('be.visible')
  })

  it('carga la página de servicios si el usuario tiene acceso', () => {
    cy.visit('/servicios')
    cy.url({ timeout: 10000 }).then((url) => {
      if (url.includes('/servicios')) {
        cy.contains('h2', 'Servicios').should('be.visible')
        cy.get('input[name="search"]').first().should('be.visible')
      } else {
        cy.log('Usuario demo sin acceso a servicios (CitasGuard) — redirigido correctamente')
        expect(url).not.to.include('/login')
      }
    })
  })
})
