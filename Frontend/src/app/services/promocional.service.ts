import { Injectable } from '@angular/core';

export interface CodigoPromocional {
    codigo: string;
    descuento: number; // Porcentaje de descuento (0-100)
    campania?: string; // Campaña asociada (opcional)
    activo: boolean; // Si el código está activo
}

@Injectable({
    providedIn: 'root'
})
export class PromocionalService {
    
    // Configuración de códigos promocionales
    // Para agregar nuevos códigos, simplemente añádelos a este array
    private codigosPromocionales: CodigoPromocional[] = [
        {
            codigo: 'SMARTPYME2025',
            descuento: 50,
            campania: 'Boxful',
            activo: true
        }
        // Aquí puedes agregar más códigos promocionales en el futuro:
        // {
        //     codigo: 'DESCUENTO20',
        //     descuento: 20,
        //     campania: 'Verano2025',
        //     activo: true
        // },
        // {
        //     codigo: 'PRIMERMES',
        //     descuento: 30,
        //     campania: 'NuevosUsuarios',
        //     activo: true
        // }
    ];

    /**
     * Obtiene la información de un código promocional
     * @param codigo Código promocional a buscar
     * @returns Información del código promocional o null si no existe
     */
    obtenerCodigoPromocional(codigo: string): CodigoPromocional | null {
        if (!codigo) return null;
        
        const codigoUpper = codigo.toUpperCase().trim();
        const codigoPromo = this.codigosPromocionales.find(
            cp => cp.codigo.toUpperCase() === codigoUpper && cp.activo
        );
        
        return codigoPromo || null;
    }

    /**
     * Verifica si un código promocional es válido
     * @param codigo Código promocional a verificar
     * @returns true si el código es válido y está activo
     */
    esCodigoValido(codigo: string): boolean {
        return this.obtenerCodigoPromocional(codigo) !== null;
    }

    /**
     * Calcula el precio con descuento aplicado
     * @param precioOriginal Precio original
     * @param codigo Código promocional
     * @returns Precio con descuento aplicado
     */
    calcularPrecioConDescuento(precioOriginal: number, codigo: string): number {
        const codigoPromo = this.obtenerCodigoPromocional(codigo);
        if (!codigoPromo) return precioOriginal;
        
        const descuento = codigoPromo.descuento / 100;
        return precioOriginal * (1 - descuento);
    }

    /**
     * Obtiene el porcentaje de descuento de un código
     * @param codigo Código promocional
     * @returns Porcentaje de descuento o 0 si no es válido
     */
    obtenerPorcentajeDescuento(codigo: string): number {
        const codigoPromo = this.obtenerCodigoPromocional(codigo);
        return codigoPromo ? codigoPromo.descuento : 0;
    }

    /**
     * Obtiene la campaña asociada a un código promocional
     * @param codigo Código promocional
     * @returns Nombre de la campaña o null
     */
    obtenerCampania(codigo: string): string | null {
        const codigoPromo = this.obtenerCodigoPromocional(codigo);
        return codigoPromo?.campania || null;
    }
}

