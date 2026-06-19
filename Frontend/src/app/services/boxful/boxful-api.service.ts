import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from '@services/api.service';

export interface BoxfulCity {
  id: string;
  name: string;
  latitude?: number;
  longitude?: number;
}

export interface BoxfulState {
  id: string;
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

  /**
   * Obtiene las direcciones de origen locales de la empresa.
   */
  getLocalOriginAddresses(): Observable<any[]> {
    return this.api.getAll('boxful/direcciones-origen');
  }

  /**
   * Obtiene los detalles de contacto de un cliente.
   */
  getClientDetails(clienteId: number): Observable<any> {
    return this.api.read('cliente/', clienteId);
  }

  /**
   * Guarda una dirección de origen localmente y en Boxful.
   */
  storeLocalOriginAddress(data: any): Observable<any> {
    return this.api.store('boxful/direcciones-origen', data);
  }

  /**
   * Cotiza las paqueterías disponibles en Boxful.
   */
  getCouriersAvailable(data: any): Observable<any> {
    return this.api.store('boxful/courier/available', data);
  }

  /**
   * Crea un nuevo envío (shipment) en Boxful.
   */
  createShipment(data: any): Observable<any> {
    return this.api.store('boxful/shipment', data);
  }

  /**
   * Obtiene los detalles de un envío en Boxful por su ID.
   */
  getShipment(id: string): Observable<any> {
    return this.api.getAll(`boxful/shipment/${id}`);
  }

  /**
   * Obtiene la información de rastreo en Boxful por el shipment number.
   */
  getTracking(id: string): Observable<any> {
    return this.api.getAll(`boxful/tracking/${id}`);
  }
}
