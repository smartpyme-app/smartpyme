/// <reference lib="webworker" />

/**
 * Web Worker para búsquedas pesadas de proveedores
 * Evita bloquear el hilo principal de la UI
 */

interface SearchMessage {
  type: 'SEARCH_PROVEEDOR' | 'SEARCH_NIT' | 'SEARCH_NRC' | 'SEARCH_DUI' | 'SEARCH_NOMBRE';
  proveedores?: any[];
  proveedor?: any;
  nit?: string;
  nrc?: string;
  dui?: string;
  nombre?: string;
  id?: string;
}

addEventListener('message', ({ data }: { data: SearchMessage }) => {
  const { type, proveedores, proveedor, nit, nrc, dui, nombre, id } = data;

  try {
    let result: any = null;

    switch (type) {
      case 'SEARCH_PROVEEDOR':
        if (!proveedor || !proveedores) {
          postMessage({ id, result: null, error: 'Datos incompletos' });
          return;
        }
        result = searchProveedor(proveedor, proveedores);
        break;

      case 'SEARCH_NIT':
        if (!nit || !proveedores) {
          postMessage({ id, result: null, error: 'Datos incompletos' });
          return;
        }
        result = searchNit(nit, proveedores);
        break;

      case 'SEARCH_NRC':
        if (!nrc || !proveedores) {
          postMessage({ id, result: null, error: 'Datos incompletos' });
          return;
        }
        result = searchNrc(nrc, proveedores);
        break;

      case 'SEARCH_DUI':
        if (!dui || !proveedores) {
          postMessage({ id, result: null, error: 'Datos incompletos' });
          return;
        }
        result = searchDui(dui, proveedores);
        break;

      case 'SEARCH_NOMBRE':
        if (!nombre || !proveedores) {
          postMessage({ id, result: null, error: 'Datos incompletos' });
          return;
        }
        result = searchNombre(nombre, proveedores);
        break;

      default:
        postMessage({ id, result: null, error: 'Tipo de búsqueda no válido' });
        return;
    }

    postMessage({ id, result, error: null });
  } catch (error: any) {
    postMessage({ id, result: null, error: error.message || 'Error desconocido' });
  }
});

function searchProveedor(proveedor: any, proveedores: any[]): any {
  let proveedorEncontrado: any = null;

  // 1. Buscar primero por NIT (prioridad más alta)
  if (proveedor.nit) {
    proveedorEncontrado = searchNit(proveedor.nit, proveedores);
    if (proveedorEncontrado) {
      return proveedorEncontrado;
    }
  }

  // 2. Buscar por NRC si no se encontró por NIT
  if (proveedor.nrc && !proveedorEncontrado) {
    proveedorEncontrado = searchNrc(proveedor.nrc, proveedores);
    if (proveedorEncontrado) {
      return proveedorEncontrado;
    }
  }

  // 3. Buscar por DUI si no se encontró por NIT ni NRC
  if (proveedor.dui && !proveedorEncontrado) {
    proveedorEncontrado = searchDui(proveedor.dui, proveedores);
    if (proveedorEncontrado) {
      return proveedorEncontrado;
    }
  }

  // 4. Como último recurso, buscar por nombre
  if (!proveedorEncontrado && proveedor.nombre) {
    proveedorEncontrado = searchNombre(proveedor.nombre, proveedores);
    if (proveedorEncontrado) {
      return proveedorEncontrado;
    }
  }

  return proveedorEncontrado;
}

function searchNit(nit: string, proveedores: any[]): any {
  return proveedores.find((p: any) => p.nit === nit || p.nit == nit) || null;
}

function searchNrc(nrc: string, proveedores: any[]): any {
  return proveedores.find((p: any) => p.ncr === nrc || p.ncr == nrc) || null;
}

function searchDui(dui: string, proveedores: any[]): any {
  return proveedores.find((p: any) => p.dui === dui || p.dui == dui) || null;
}

function searchNombre(nombre: string, proveedores: any[]): any {
  return proveedores.find((p: any) =>
    p.nombre_empresa === nombre ||
    p.nombre_empresa == nombre ||
    p.nombre === nombre ||
    p.nombre == nombre
  ) || null;
}

