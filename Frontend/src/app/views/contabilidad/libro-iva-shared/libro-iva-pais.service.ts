import { Injectable } from '@angular/core';
import { Router } from '@angular/router';
import { ApiService } from '@services/api.service';
import { FeCrUbicacionService } from '@services/fe-cr-ubicacion.service';

export type LibroIvaPaisTipo = 'sv' | 'cr' | 'hd' | 'general';

@Injectable({ providedIn: 'root' })
export class LibroIvaPaisService {
  constructor(
    private apiService: ApiService,
    private feCrUbic: FeCrUbicacionService
  ) {}

  empresaPais(): string {
    return this.apiService.auth_user()?.empresa?.pais ?? '';
  }

  esElSalvador(): boolean {
    return this.empresaPais() === 'El Salvador';
  }

  esHonduras(): boolean {
    return this.empresaPais() === 'Honduras';
  }

  esCostaRica(): boolean {
    return this.feCrUbic.esCostaRicaFe();
  }

  tipoLibroIva(): LibroIvaPaisTipo {
    if (this.esElSalvador()) {
      return 'sv';
    }
    if (this.esCostaRica()) {
      return 'cr';
    }
    if (this.esHonduras()) {
      return 'hd';
    }
    return 'general';
  }

  rutaInicioLibroIva(): string[] {
    switch (this.tipoLibroIva()) {
      case 'sv':
        return ['/libro-iva-sv/contribuyentes'];
      case 'cr':
        return ['/libro-iva-cr/ventas'];
      case 'hd':
        return ['/libro-iva-hd/ventas'];
      default:
        return ['/libro-iva-general/ventas'];
    }
  }

  /** Redirige al libro IVA del país correcto si el componente no corresponde. */
  redirigirSiPaisIncorrecto(esperado: LibroIvaPaisTipo, router: Router): boolean {
    if (this.tipoLibroIva() === esperado) {
      return false;
    }
    void router.navigate(this.rutaInicioLibroIva(), { replaceUrl: true });
    return true;
  }
}
