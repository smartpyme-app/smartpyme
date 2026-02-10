import { Injectable } from '@angular/core';
import { Observable, of } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { ApiService } from '@services/api.service';

export interface CodigoPromocional {
    codigo: string;
    descuento: number; // Porcentaje de descuento (0-100)
    tipo: 'porcentaje' | 'monto_fijo';
    campania?: string; // Campaña asociada (opcional)
    descripcion?: string;
    planes_permitidos?: string[];
    opciones?: {
        uso_maximo?: number | null;
        uso_por_usuario?: number;
        fecha_inicio?: string;
        fecha_expiracion?: string;
        monto_minimo?: number | null;
        monto_maximo?: number | null;
        combinable?: boolean;
    };
}

export interface ValidacionCodigoResponse {
    valido: boolean;
    mensaje?: string;
    codigo?: string;
    descuento?: number;
    tipo?: 'porcentaje' | 'monto_fijo';
    campania?: string;
    descripcion?: string;
    planes_permitidos?: string[];
    opciones?: any;
}

@Injectable({
    providedIn: 'root'
})
export class PromocionalService {
    private cache: Map<string, CodigoPromocional | null> = new Map();

    constructor(private apiService: ApiService) {}

    /**
     * Valida y obtiene la información de un código promocional desde la API
     * @param codigo Código promocional a validar
     * @param tipoPlan Tipo de plan para validar planes permitidos (opcional)
     * @returns Observable con la información del código promocional o null si no es válido
     */
    validarCodigo(codigo: string, tipoPlan?: string): Observable<CodigoPromocional | null> {
        if (!codigo) {
            return of(null);
        }

        // Verificar cache
        const cacheKey = `${codigo}_${tipoPlan || ''}`;
        if (this.cache.has(cacheKey)) {
            return of(this.cache.get(cacheKey)!);
        }

        return this.apiService.store('promocional/validar', { codigo, tipo_plan: tipoPlan }).pipe(
            map((response: ValidacionCodigoResponse) => {
                if (response && response.valido && response.codigo) {
                    const codigoPromo: CodigoPromocional = {
                        codigo: response.codigo,
                        descuento: response.descuento || 0,
                        tipo: response.tipo || 'porcentaje',
                        campania: response.campania,
                        descripcion: response.descripcion,
                        planes_permitidos: response.planes_permitidos,
                        opciones: response.opciones
                    };
                    this.cache.set(cacheKey, codigoPromo);
                    return codigoPromo;
                }
                this.cache.set(cacheKey, null);
                return null;
            }),
            catchError(error => {
                console.error('Error al validar código promocional:', error);
                this.cache.set(cacheKey, null);
                return of(null);
            })
        );
    }

    /**
     * Obtiene la información de un código promocional (método síncrono con cache)
     * @param codigo Código promocional a buscar
     * @returns Información del código promocional o null si no existe
     * @deprecated Usar validarCodigo() para obtener datos actualizados desde la API
     */
    obtenerCodigoPromocional(codigo: string): CodigoPromocional | null {
        if (!codigo) return null;
        
        const cacheKey = `${codigo}_`;
        return this.cache.get(cacheKey) || null;
    }

    /**
     * Verifica si un código promocional es válido
     * @param codigo Código promocional a verificar
     * @param tipoPlan Tipo de plan para validar planes permitidos (opcional)
     * @returns Observable<boolean> true si el código es válido y está activo
     */
    esCodigoValido(codigo: string, tipoPlan?: string): Observable<boolean> {
        return this.validarCodigo(codigo, tipoPlan).pipe(
            map(codigoPromo => codigoPromo !== null)
        );
    }

    /**
     * Calcula el precio con descuento aplicado
     * @param precioOriginal Precio original
     * @param codigoPromo Código promocional validado
     * @returns Precio con descuento aplicado
     */
    calcularPrecioConDescuento(precioOriginal: number, codigoPromo: CodigoPromocional | null): number {
        if (!codigoPromo) return precioOriginal;
        
        if (codigoPromo.tipo === 'porcentaje') {
        const descuento = codigoPromo.descuento / 100;
        return precioOriginal * (1 - descuento);
        } else if (codigoPromo.tipo === 'monto_fijo') {
            return Math.max(0, precioOriginal - codigoPromo.descuento);
        }
        
        return precioOriginal;
    }

    /**
     * Obtiene el porcentaje de descuento de un código
     * @param codigoPromo Código promocional validado
     * @returns Porcentaje de descuento o 0 si no es válido
     */
    obtenerPorcentajeDescuento(codigoPromo: CodigoPromocional | null): number {
        if (!codigoPromo || codigoPromo.tipo !== 'porcentaje') {
            return 0;
        }
        return codigoPromo.descuento;
    }

    /**
     * Obtiene la campaña asociada a un código promocional
     * @param codigoPromo Código promocional validado
     * @returns Nombre de la campaña o null
     */
    obtenerCampania(codigoPromo: CodigoPromocional | null): string | null {
        return codigoPromo?.campania || null;
    }

    /**
     * Limpia el cache de códigos promocionales
     */
    limpiarCache(): void {
        this.cache.clear();
    }
}

