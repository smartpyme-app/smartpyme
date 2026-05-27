import { Injectable } from '@angular/core';
import { firstValueFrom } from 'rxjs';
import { ApiService } from '@services/api.service';
import { AlertService } from '@services/alert.service';
import { ProveedorSearchService } from '@workers/proveedor-search.service';

export interface ProveedorDesdeEmisorResult {
  proveedor: any | null;
  creado: boolean;
}

/**
 * Busca proveedor por datos del emisor del DTE/XML y, si no existe, lo crea.
 */
@Injectable({ providedIn: 'root' })
export class ProveedorDesdeEmisorService {
  constructor(
    private apiService: ApiService,
    private alertService: AlertService,
    private proveedorSearchService: ProveedorSearchService
  ) {}

  /** Normaliza emisor MH / CR a campos usados en proveedores. */
  normalizarEmisor(emisor: any): Record<string, unknown> {
    if (!emisor || typeof emisor !== 'object') {
      return {};
    }
    const nitRaw = emisor['nit'] ?? emisor['identificacion'] ?? '';
    const nit = String(nitRaw).replace(/\s/g, '').trim();
    const nombre = String(emisor['nombre'] ?? emisor['nombre_empresa'] ?? '').trim();
    let direccionComplemento = '';
    const dir = emisor['direccion'];
    if (dir && typeof dir === 'object' && dir['complemento']) {
      direccionComplemento = String(dir['complemento']);
    } else if (typeof dir === 'string') {
      direccionComplemento = dir;
    }

    return {
      nit,
      nrc: emisor['nrc'] ?? '',
      dui: emisor['dui'] ?? '',
      nombre,
      telefono: emisor['telefono'] ?? '',
      correo: emisor['correo'] ?? emisor['email'] ?? '',
      direccion: { complemento: direccionComplemento || 'No especificada' },
    };
  }

  /**
   * Busca en lista local; si no hay match y hay datos mínimos, crea proveedor empresa.
   */
  async buscarOcrear(
    emisorRaw: any,
    proveedores: any[],
    opciones?: { notificarCreacion?: boolean }
  ): Promise<ProveedorDesdeEmisorResult> {
    const emisor = this.normalizarEmisor(emisorRaw);
    const notificar = opciones?.notificarCreacion !== false;

    if (!emisor['nit'] && !emisor['nombre']) {
      return { proveedor: null, creado: false };
    }

    try {
      const encontrado = await firstValueFrom(
        this.proveedorSearchService.searchProveedor(emisor, proveedores || [])
      );
      if (encontrado?.id) {
        return { proveedor: encontrado, creado: false };
      }
    } catch {
      // continuar a creación
    }

    const auth = this.apiService.auth_user();
    const nombreEmpresa = String(
      emisor['nombre'] || `Proveedor ${emisor['nit'] || 'importado'}`
    ).trim();
    const payload = {
      tipo: 'Empresa',
      nombre_empresa: nombreEmpresa,
      nit: String(emisor['nit'] ?? ''),
      nrc: String(emisor['nrc'] ?? ''),
      telefono: String(emisor['telefono'] ?? ''),
      email: String(emisor['correo'] ?? ''),
      direccion:
        (emisor['direccion'] as { complemento?: string })?.complemento || 'No especificada',
      id_empresa: auth?.id_empresa,
      id_usuario: auth?.id,
    };

    if (!nombreEmpresa) {
      return { proveedor: null, creado: false };
    }

    try {
      const creado = await firstValueFrom(this.apiService.store('proveedor', payload));
      if (creado?.id) {
        if (!proveedores.find((p: any) => p.id === creado.id)) {
          proveedores.push(creado);
        }
        if (notificar) {
          this.alertService.success(
            'Proveedor creado',
            `Se registró automáticamente: ${nombreEmpresa}`
          );
        }
        return { proveedor: creado, creado: true };
      }
    } catch (e: unknown) {
      const msg =
        (e as { error?: { message?: string } })?.error?.message ||
        'No se pudo crear el proveedor automáticamente.';
      this.alertService.warning('Proveedor', msg);
    }

    return { proveedor: null, creado: false };
  }
}
