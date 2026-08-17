import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { ApiService } from './api.service';

export interface GiftCardLookup {
  id: number;
  codigo: string;
  saldo: number;
  estado: string;
  monto_inicial: number;
  fecha_emision?: string | null;
  fecha_vencimiento?: string | null;
}

export interface GiftCardApiResponse<T> {
  success: boolean;
  data: T;
  message?: string;
}

export interface GiftCardListResponse {
  success: boolean;
  data: GiftCardLookup[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

@Injectable({
  providedIn: 'root',
})
export class GiftCardsService {
  constructor(private apiService: ApiService) {}

  getByCodigo(codigo: string): Observable<GiftCardApiResponse<GiftCardLookup>> {
    const trimmed = encodeURIComponent(codigo.trim());
    return this.apiService.get(`gift-cards/by-codigo/${trimmed}`);
  }

  list(filtros: Record<string, unknown> = {}): Observable<GiftCardListResponse> {
    return this.apiService.getAll('gift-cards', filtros);
  }
}
