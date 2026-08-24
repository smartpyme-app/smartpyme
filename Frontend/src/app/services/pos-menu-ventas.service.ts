import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

const BASE = 'inventario/pos-menu';

export interface PosMenuVentasCategoria {
  id: number;
  nombre: string;
  img?: string | null;
  subcategorias_count: number;
}

export interface PosMenuVentasSubcategoria {
  id: number;
  nombre: string;
  img?: string | null;
}

export interface PosMenuVentasProducto {
  id: number;
  nombre: string;
  /** Precio con IVA para tiles v2. */
  precio: number;
  img?: string | null;
  tipo: string;
  genera_comanda?: boolean;
  id_presentacion?: number | null;
}

export interface PosMenuVentasContenido {
  modo: 'subcategorias' | 'productos';
  items: PosMenuVentasSubcategoria[] | PosMenuVentasProducto[];
}

@Injectable({ providedIn: 'root' })
export class PosMenuVentasService {
  constructor(private api: ApiService) {}

  categorias(params?: { id_bodega?: number }): Observable<PosMenuVentasCategoria[]> {
    return this.api.getAll(`${BASE}/categorias`, params || {});
  }

  contenidoCategoria(id: number, params?: { id_bodega?: number }): Observable<PosMenuVentasContenido> {
    return this.api.getAll(`${BASE}/categorias/${id}/contenido`, params || {});
  }

  productosSubcategoria(id: number, params?: { id_bodega?: number }): Observable<PosMenuVentasProducto[]> {
    return this.api.getAll(`${BASE}/subcategorias/${id}/productos`, params || {});
  }

  buscar(q: string, params?: { id_bodega?: number }): Observable<PosMenuVentasProducto[]> {
    return this.api.getAll(`${BASE}/buscar`, { q, ...(params || {}) });
  }

  productoParaVenta(id: number, params?: { id_bodega?: number; id_presentacion?: number | null }): Observable<any> {
    return this.api.getAll(`${BASE}/productos/${id}`, params || {});
  }
}
