import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface BoxfulCity {
  id: number;
  name: string;
}

export interface BoxfulState {
  id: number;
  name: string;
  Cities: BoxfulCity[]; // Notar la "C" mayúscula proveniente de la API
}

export interface BoxfulAddress {
  id: number;
  address: string;
  referencePoint?: string;
  latitude: number;
  longitude: number;
  stateId: number;
  cityId: number;
  addressPhone: string;
  addressAreaCode: string;
  state?: { id: number; name: string };
  city?: { id: number; name: string };
}

@Injectable({
  providedIn: 'root'
})
export class BoxfulApiService {

  constructor(private api: ApiService) { }

  /**
   * Obtiene la lista de estados y ciudades de Boxful.
   */
  getStates(): Observable<BoxfulState[]> {
    return this.api.getAll('boxful/states');
  }

  /**
   * Obtiene las direcciones de la empresa activa guardadas en Boxful.
   */
  getAddresses(): Observable<BoxfulAddress[]> {
    return this.api.getAll('boxful/addresses');
  }

  /**
   * Registra una nueva dirección en Boxful.
   */
  createAddress(data: Partial<BoxfulAddress>): Observable<BoxfulAddress> {
    return this.api.store('boxful/addresses', data);
  }

  /**
   * Actualiza una dirección existente en Boxful.
   */
  updateAddress(id: number, data: Partial<BoxfulAddress>): Observable<BoxfulAddress> {
    return this.api.patch('boxful/addresses', id, data);
  }

  /**
   * Elimina una dirección de Boxful por ID.
   */
  deleteAddress(id: number): Observable<any> {
    return this.api.delete('boxful/addresses/', id);
  }
}
