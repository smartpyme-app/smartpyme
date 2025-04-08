import { Injectable } from '@angular/core';
import { ApiService } from '../services/api.service';

@Injectable({
    providedIn: 'root'
})
export class PlanillaConstants {
    private static get constants() {
        const constants = localStorage.getItem('SP_constants');
        return constants ? JSON.parse(constants).planilla.original : null;
    }

    // Estados básicos
    static get ESTADOS() {
        return this.constants?.ESTADOS || {};
    }

    // Estados específicos
    static get ESTADOS_EMPLEADO() {
        return this.constants?.ESTADOS_EMPLEADO || {};
    }

    static get ESTADOS_PLANILLA() {
        return this.constants?.ESTADOS_PLANILLA || {};
    }

    // Tipos
    static get TIPOS_CONTRATO() {
        return this.constants?.TIPOS_CONTRATO || {};
    }

    static get TIPOS_JORNADA() {
        return this.constants?.TIPOS_JORNADA || {};
    }

    static get TIPO_DOCUMENTO() {
        return this.constants?.TIPO_DOCUMENTO || {};
    }

    // Configuraciones financieras
    static get DESCUENTOS() {
        return this.constants?.DESCUENTOS || {};
    }

    static get RENTA() {
        return this.constants?.RENTA || {};
    }

    // Listas y mapeos
    static get LISTAS() {
        return this.constants?.LISTAS || {};
    }

    // Métodos para obtener nombres
    static getNombreEstadoEmpleado(estado: number): string {
        return this.LISTAS.ESTADOS_EMPLEADO?.[estado] || 'Desconocido';
    }

    static getNombreTipoContrato(id: number): string {
        return this.LISTAS.TIPOS_CONTRATO?.[id] || 'Desconocido';
    }

    static getNombreTipoJornada(id: number): string {
        return this.LISTAS.TIPOS_JORNADA?.[id] || 'Desconocido';
    }

    static getNombreTipoBaja(id: number): string {
        return this.LISTAS.TIPOS_BAJA?.[id] || 'Desconocido';
    }

    static getNombreTipoDocumento(id: number): string {
        return this.LISTAS.TIPOS_DOCUMENTO?.[id] || 'Desconocido';
    }

    // Métodos para obtener opciones para selects
    static getEstadosEmpleadoOptions(): Array<{id: number, nombre: string}> {
        return Object.entries(this.LISTAS.ESTADOS_EMPLEADO || {})
            .map(([id, nombre]) => ({
                id: Number(id),
                nombre: String(nombre)
            }));
    }

    static getTiposContratoOptions(): Array<{id: number, nombre: string}> {
        return Object.entries(this.LISTAS.TIPOS_CONTRATO || {})
            .map(([id, nombre]) => ({
                id: Number(id),
                nombre: String(nombre)
            }));
    }

    static getTiposJornadaOptions(): Array<{id: number, nombre: string}> {
        return Object.entries(this.LISTAS.TIPOS_JORNADA || {})
            .map(([id, nombre]) => ({
                id: Number(id),
                nombre: String(nombre)
            }));
    }

    static getTiposBajaOptions(): Array<{id: number, nombre: string}> {
        return Object.entries(this.LISTAS.TIPOS_BAJA || {})
            .map(([id, nombre]) => ({
                id: Number(id),
                nombre: String(nombre)
            }));
    }

    static getTiposDocumentoOptions(): Array<{id: number, nombre: string}> {
        return Object.entries(this.LISTAS.TIPOS_DOCUMENTO || {})
            .map(([id, nombre]) => ({
                id: Number(id),
                nombre: String(nombre)
            }));
    }

    // Métodos de validación
    static isEstadoEmpleadoValido(estado: number): boolean {
        return Object.keys(this.ESTADOS_EMPLEADO).includes(estado.toString());
    }

    static isTipoContratoValido(tipo: number): boolean {
        return Object.keys(this.TIPOS_CONTRATO).includes(tipo.toString());
    }

    static isTipoJornadaValido(tipo: number): boolean {
        return Object.keys(this.TIPOS_JORNADA).includes(tipo.toString());
    }

    // Métodos de cálculo
    static calcularDescuentoISSSEmpleado(salario: number): number {
        return salario * this.DESCUENTOS.ISSS_EMPLEADO;
    }

    static calcularDescuentoISSSPatronal(salario: number): number {
        return salario * this.DESCUENTOS.ISSS_PATRONO;
    }

    static calcularDescuentoAFPEmpleado(salario: number): number {
        return salario * this.DESCUENTOS.AFP_EMPLEADO;
    }

    static calcularDescuentoAFPPatronal(salario: number): number {
        return salario * this.DESCUENTOS.AFP_PATRONO;
    }

    static calcularRenta(salarioNeto: number): number {
        if (salarioNeto <= this.RENTA.MINIMA) {
            return 0;
        } else if (salarioNeto <= this.RENTA.MAXIMA_PRIMER_TRAMO) {
            return (salarioNeto - this.RENTA.MINIMA) * this.RENTA.PORCENTAJE_PRIMER_TRAMO;
        } else if (salarioNeto <= this.RENTA.MAXIMA_SEGUNDO_TRAMO) {
            return (salarioNeto - this.RENTA.MAXIMA_PRIMER_TRAMO) * this.RENTA.PORCENTAJE_SEGUNDO_TRAMO;
        } else {
            return (salarioNeto - this.RENTA.MAXIMA_SEGUNDO_TRAMO) * this.RENTA.PORCENTAJE_TERCER_TRAMO;
        }
    }

    // Método para verificar si las constantes están cargadas
    static isLoaded(): boolean {
        return !!this.constants;
    }

    // Método para actualizar constantes si es necesario
    static updateConstants(constants: any) {
        if (constants?.planilla?.original) {
            localStorage.setItem('SP_constants', JSON.stringify(constants));
        }
    }
}