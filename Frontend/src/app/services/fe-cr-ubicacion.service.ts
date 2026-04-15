import { Injectable } from '@angular/core';
import { forkJoin, Observable, of } from 'rxjs';
import { tap } from 'rxjs/operators';
import { ApiService } from '@services/api.service';
import { FE_PAIS_CR, resolveCodigoPaisFe } from '@services/facturacion-electronica/fe-pais.util';

/**
 * Catálogo territorial INEC/DGT (mismos endpoints y localStorage que MH en SV).
 * Usado en empresa, clientes y proveedores cuando la cuenta es Costa Rica FE.
 */
@Injectable({ providedIn: 'root' })
export class FeCrUbicacionService {
  constructor(private readonly api: ApiService) {}

  esCostaRicaFe(): boolean {
    return resolveCodigoPaisFe(this.api.auth_user()?.empresa) === FE_PAIS_CR;
  }

  municipiosPorProvincia(municipios: any[], codDepartamento: unknown): any[] {
    if (codDepartamento === undefined || codDepartamento === null || codDepartamento === '') {
      return [];
    }
    return (municipios || []).filter((m: any) => String(m.cod_departamento) === String(codDepartamento));
  }

  distritosPorCanton(distritos: any[], codDepartamento: unknown, codMunicipio: unknown): any[] {
    if (
      codMunicipio === undefined ||
      codMunicipio === null ||
      codMunicipio === '' ||
      codDepartamento === undefined ||
      codDepartamento === null ||
      codDepartamento === ''
    ) {
      return [];
    }
    return (distritos || []).filter(
      (x: any) =>
        String(x.cod_municipio) === String(codMunicipio) &&
        String(x.cod_departamento) === String(codDepartamento),
    );
  }

  /** Si la sesión es CR FE, descarga provincias/cantones/distritos y guarda en localStorage. */
  cargarCatalogosYLs(): Observable<{ dep: any[]; mun: any[]; dis: any[] } | null> {
    if (!this.esCostaRicaFe()) {
      return of(null);
    }
    return forkJoin({
      dep: this.api.getAll('fe-cr/departamentos'),
      mun: this.api.getAll('fe-cr/municipios'),
      dis: this.api.getAll('fe-cr/distritos'),
    }).pipe(
      tap((r) => {
        localStorage.setItem('departamentos', JSON.stringify(r.dep));
        localStorage.setItem('municipios', JSON.stringify(r.mun));
        localStorage.setItem('distritos', JSON.stringify(r.dis));
      }),
    );
  }
}
