import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface ContadorEmpresaPortafolio {
  acceso_id: number;
  id: number;
  nombre: string;
  logo: string | null;
  giro?: string | null;
  permisos: string[];
}

export interface ContadorEmpresaReciente {
  id: number;
  nombre: string;
  logo: string | null;
  accedidoEn: number;
}

const KEY_EMPRESA_ACTIVA = 'SP_contador_empresa_activa';
const KEY_RECIENTES = 'SP_contador_empresas_recientes';

@Injectable({ providedIn: 'root' })
export class ContadoresPortalService {
  constructor(private api: ApiService) {}

  listarEmpresas(): Observable<{ empresas: ContadorEmpresaPortafolio[] }> {
    return this.api.get('contadores/empresas');
  }

  guardarEmpresaActiva(idEmpresa: number): void {
    localStorage.setItem(KEY_EMPRESA_ACTIVA, String(idEmpresa));
  }

  leerRecientes(): ContadorEmpresaReciente[] {
    try {
      const raw = localStorage.getItem(KEY_RECIENTES);
      return raw ? (JSON.parse(raw) as ContadorEmpresaReciente[]) : [];
    } catch {
      return [];
    }
  }

  registrarAccesoReciente(empresa: ContadorEmpresaPortafolio): void {
    const prev = this.leerRecientes();
    const next: ContadorEmpresaReciente[] = [
      {
        id: empresa.id,
        nombre: empresa.nombre,
        logo: empresa.logo,
        accedidoEn: Date.now(),
      },
      ...prev.filter((r) => r.id !== empresa.id),
    ].slice(0, 3);
    localStorage.setItem(KEY_RECIENTES, JSON.stringify(next));
  }
}
