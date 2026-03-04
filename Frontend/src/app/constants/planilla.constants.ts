import { Injectable } from '@angular/core';

@Injectable({
    providedIn: 'root'
})
export class PlanillaConstants {
    public static get constants() {
        const constants = localStorage.getItem('SP_constants');
        return constants ? JSON.parse(constants).planilla.original : null;
    }

    // ==================== ESTADOS BÁSICOS ====================
    static get ESTADOS() {
        return this.constants?.ESTADOS || {};
    }

    static get ESTADOS_EMPLEADO() {
        return this.constants?.ESTADOS_EMPLEADO || {};
    }

    static get ESTADOS_PLANILLA() {
        return this.constants?.ESTADOS_PLANILLA || {};
    }

    // ==================== TIPOS ====================
    static get TIPOS_CONTRATO() {
        return this.constants?.TIPOS_CONTRATO || {};
    }

    static get TIPOS_JORNADA() {
        return this.constants?.TIPOS_JORNADA || {};
    }

    static get TIPO_DOCUMENTO() {
        return this.constants?.TIPO_DOCUMENTO || {};
    }

    // ==================== CONFIGURACIONES FINANCIERAS ====================
    static get DESCUENTOS() {
        return this.constants?.DESCUENTOS || {};
    }

    static get RENTA() {
        return this.constants?.RENTA || {};
    }

    // ==================== AGUINALDOS ====================
    static get AGUINALDO() {
        return this.constants?.AGUINALDO || {};
    }

    // ==================== NUEVAS TABLAS 2025 ====================
    static get RENTA_TABLAS() {
        return this.constants?.RENTA_TABLAS || {};
    }

    static get RECALCULOS() {
        return this.constants?.RECALCULOS || {};
    }

    static get DEDUCCION_EMPLEADOS_ASALARIADOS() {
        return this.constants?.DEDUCCION_EMPLEADOS_ASALARIADOS || 1600.00;
    }

    // ==================== LISTAS Y MAPEOS ====================
    static get LISTAS() {
        return this.constants?.LISTAS || {};
    }

    // ==================== MÉTODOS PARA OBTENER NOMBRES ====================
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

    // ==================== MÉTODOS PARA OBTENER OPCIONES PARA SELECTS ====================
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

    // ==================== MÉTODOS DE VALIDACIÓN ====================
    static isEstadoEmpleadoValido(estado: number): boolean {
        return Object.keys(this.ESTADOS_EMPLEADO).includes(estado.toString());
    }

    static isTipoContratoValido(tipo: number): boolean {
        return Object.keys(this.TIPOS_CONTRATO).includes(tipo.toString());
    }

    static isTipoJornadaValido(tipo: number): boolean {
        return Object.keys(this.TIPOS_JORNADA).includes(tipo.toString());
    }

    // ==================== NUEVOS MÉTODOS CON TABLAS 2025 ====================

    /**
     * Obtiene los tramos de renta según el tipo de planilla
     */
    static getTramosRenta(tipoPlanilla: string = 'mensual') {
        const tablas = this.RENTA_TABLAS;
        
        switch (tipoPlanilla.toLowerCase()) {
            case 'quincenal':
                return tablas?.QUINCENAL || {};
            case 'semanal':
                return tablas?.SEMANAL || {};
            default:
                return tablas?.MENSUAL || {};
        }
    }

    /**
     * Calcula la retención de renta según las nuevas tablas 2025
     */
    static calcularRetencionRenta(salarioGravado: number, tipoPlanilla: string = 'mensual'): number {
        // Redondear el salario gravado a 2 decimales
        salarioGravado = Math.round(salarioGravado * 100) / 100;
        
        // Si el salario gravado es 0 o negativo, no hay retención
        if (salarioGravado <= 0) {
            return 0.00;
        }

        const tramos = this.getTramosRenta(tipoPlanilla);
        
        // Buscar el tramo correspondiente
        if (salarioGravado >= tramos.TRAMO_1?.DESDE && salarioGravado <= tramos.TRAMO_1?.HASTA) {
            return 0.00; // Sin retención
        } else if (salarioGravado >= tramos.TRAMO_2?.DESDE && salarioGravado <= tramos.TRAMO_2?.HASTA) {
            const exceso = salarioGravado - tramos.TRAMO_2.SOBRE_EXCESO;
            const retencion = tramos.TRAMO_2.CUOTA_FIJA + (exceso * tramos.TRAMO_2.PORCENTAJE);
            return Math.round(retencion * 100) / 100;
        } else if (salarioGravado >= tramos.TRAMO_3?.DESDE && salarioGravado <= tramos.TRAMO_3?.HASTA) {
            const exceso = salarioGravado - tramos.TRAMO_3.SOBRE_EXCESO;
            const retencion = tramos.TRAMO_3.CUOTA_FIJA + (exceso * tramos.TRAMO_3.PORCENTAJE);
            return Math.round(retencion * 100) / 100;
        } else if (tramos.TRAMO_4) {
            // Tramo 4
            const exceso = salarioGravado - tramos.TRAMO_4.SOBRE_EXCESO;
            const retencion = tramos.TRAMO_4.CUOTA_FIJA + (exceso * tramos.TRAMO_4.PORCENTAJE);
            return Math.round(retencion * 100) / 100;
        }

        return 0.00;
    }

    /**
     * Calcula el salario gravado para efectos de renta
     */
    static calcularSalarioGravado(
        salarioDevengado: number,
        isssEmpleado: number,
        afpEmpleado: number,
        tipoPlanilla: string = 'mensual'
    ): number {
        // Según el decreto, se descuenta del salario devengado las cotizaciones a seguridad social
        let salarioGravado = salarioDevengado - isssEmpleado - afpEmpleado;
        
        // Aplicar deducción de empleados asalariados si corresponde
        const salarioAnualEstimado = this.extrapolarSalarioAnual(salarioGravado, tipoPlanilla);
        
        if (salarioAnualEstimado <= 9100.00) {
            // Calcular la deducción proporcional según el tipo de planilla
            const deduccionProporcional = this.calcularDeduccionProporcional(tipoPlanilla, this.constants);
            salarioGravado = Math.max(0, salarioGravado - deduccionProporcional);
        }
        
        return Math.round(salarioGravado * 100) / 100;
    }

    /**
     * Extrapola el salario a anual según el tipo de planilla
     */
    private static extrapolarSalarioAnual(salario: number, tipoPlanilla: string): number {
        switch (tipoPlanilla.toLowerCase()) {
            case 'quincenal':
                return salario * 24; // 24 quincenas al año
            case 'semanal':
                return salario * 52; // 52 semanas al año
            default: // mensual
                return salario * 12; // 12 meses al año
        }
    }

    
    private static calcularDeduccionProporcional(tipoPlanilla: string, constants: any): number {
        const deduccionAnual = constants.DEDUCCION_EMPLEADOS_ASALARIADOS || 1600.00;
        
        switch (tipoPlanilla?.toLowerCase()) {
            case 'quincenal':
                return Math.round((deduccionAnual / 24) * 100) / 100;
            case 'semanal':
                return Math.round((deduccionAnual / 52) * 100) / 100;
            default:
                return Math.round((deduccionAnual / 12) * 100) / 100;
        }
    }
    
    /**
     * Calcula todos los descuentos de un empleado usando las nuevas tablas
     */
    static calcularDescuentosEmpleado(
        salarioDevengado: number,
        montoHorasExtra: number,
        comisiones: number,
        bonificaciones: number,
        otrosIngresos: number,
        tipoPlanilla: string = 'mensual'
    ) {
        // Obtener constantes del backend
        const constants = this.constants;
        console.log('Constants loaded:', !!constants);
        
        if (!constants) {
          console.error('Constantes no disponibles');
          return this.getDefaultCalculation();
        }
        
        console.log('DESCUENTO_ISSS_EMPLEADO:', constants.DESCUENTO_ISSS_EMPLEADO);
        console.log('RENTA_MENSUAL_TRAMO_2_CUOTA_FIJA:', constants.RENTA_MENSUAL_TRAMO_2_CUOTA_FIJA);
        
    
        // Calcular total de ingresos
        const totalIngresos = salarioDevengado + montoHorasExtra + comisiones + bonificaciones + otrosIngresos;
    
        // Calcular ISSS con tope de $1000
        const baseISSSEmpleado = Math.min(totalIngresos, 1000);
        const isssEmpleado = Math.round(baseISSSEmpleado * constants.DESCUENTO_ISSS_EMPLEADO * 100) / 100;
        const isssPatronal = Math.round(baseISSSEmpleado * constants.DESCUENTO_ISSS_PATRONO * 100) / 100;
    
        // Calcular AFP sin tope
        const afpEmpleado = Math.round(totalIngresos * constants.DESCUENTO_AFP_EMPLEADO * 100) / 100;
        const afpPatronal = Math.round(totalIngresos * constants.DESCUENTO_AFP_PATRONO * 100) / 100;
    
        // Calcular salario gravado
        const salarioGravado = this.calcularSalarioGravadoConConstantes(totalIngresos, isssEmpleado, afpEmpleado, tipoPlanilla, constants);
        
        // Calcular renta
        const renta = this.calcularRetencionRentaConConstantes(salarioGravado, tipoPlanilla, constants);
    
        return {
            totalIngresos: Math.round(totalIngresos * 100) / 100,
            isssEmpleado,
            isssPatronal,
            afpEmpleado,
            afpPatronal,
            salarioGravado,
            renta,
            totalDescuentos: Math.round((isssEmpleado + afpEmpleado + renta) * 100) / 100,
            sueldoNeto: Math.round((totalIngresos - isssEmpleado - afpEmpleado - renta) * 100) / 100
        };
    }
    
    private static calcularSalarioGravadoConConstantes(
        totalIngresos: number, 
        isssEmpleado: number, 
        afpEmpleado: number, 
        tipoPlanilla: string,
        constants: any
    ): number {
        let salarioGravado = totalIngresos - isssEmpleado - afpEmpleado;
        
        // Aplicar deducción de empleados asalariados si corresponde
        const salarioAnualEstimado = this.extrapolarSalarioAnual(salarioGravado, tipoPlanilla);
        
        if (salarioAnualEstimado <= 9100.00) {
            const deduccionProporcional = this.calcularDeduccionProporcional(tipoPlanilla, constants);
            salarioGravado = Math.max(0, salarioGravado - deduccionProporcional);
        }
        
        return Math.round(salarioGravado * 100) / 100;
    }
    
    private static calcularRetencionRentaConConstantes(
        salarioGravado: number, 
        tipoPlanilla: string, 
        constants: any
    ): number {
        if (salarioGravado <= 0) return 0;
    
        // Obtener tramos según tipo de planilla
        let tramos = [];
        switch (tipoPlanilla?.toLowerCase()) {
            case 'quincenal':
                tramos = [
                    {
                        desde: constants.RENTA_QUINCENAL_TRAMO_1_DESDE,
                        hasta: constants.RENTA_QUINCENAL_TRAMO_1_HASTA,
                        porcentaje: constants.RENTA_QUINCENAL_TRAMO_1_PORCENTAJE,
                        sobreExceso: constants.RENTA_QUINCENAL_TRAMO_1_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_QUINCENAL_TRAMO_1_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_QUINCENAL_TRAMO_2_DESDE,
                        hasta: constants.RENTA_QUINCENAL_TRAMO_2_HASTA,
                        porcentaje: constants.RENTA_QUINCENAL_TRAMO_2_PORCENTAJE,
                        sobreExceso: constants.RENTA_QUINCENAL_TRAMO_2_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_QUINCENAL_TRAMO_2_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_QUINCENAL_TRAMO_3_DESDE,
                        hasta: constants.RENTA_QUINCENAL_TRAMO_3_HASTA,
                        porcentaje: constants.RENTA_QUINCENAL_TRAMO_3_PORCENTAJE,
                        sobreExceso: constants.RENTA_QUINCENAL_TRAMO_3_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_QUINCENAL_TRAMO_3_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_QUINCENAL_TRAMO_4_DESDE,
                        hasta: constants.RENTA_QUINCENAL_TRAMO_4_HASTA,
                        porcentaje: constants.RENTA_QUINCENAL_TRAMO_4_PORCENTAJE,
                        sobreExceso: constants.RENTA_QUINCENAL_TRAMO_4_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_QUINCENAL_TRAMO_4_CUOTA_FIJA
                    }
                ];
                break;
            case 'semanal':
                tramos = [
                    {
                        desde: constants.RENTA_SEMANAL_TRAMO_1_DESDE,
                        hasta: constants.RENTA_SEMANAL_TRAMO_1_HASTA,
                        porcentaje: constants.RENTA_SEMANAL_TRAMO_1_PORCENTAJE,
                        sobreExceso: constants.RENTA_SEMANAL_TRAMO_1_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_SEMANAL_TRAMO_1_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_SEMANAL_TRAMO_2_DESDE,
                        hasta: constants.RENTA_SEMANAL_TRAMO_2_HASTA,
                        porcentaje: constants.RENTA_SEMANAL_TRAMO_2_PORCENTAJE,
                        sobreExceso: constants.RENTA_SEMANAL_TRAMO_2_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_SEMANAL_TRAMO_2_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_SEMANAL_TRAMO_3_DESDE,
                        hasta: constants.RENTA_SEMANAL_TRAMO_3_HASTA,
                        porcentaje: constants.RENTA_SEMANAL_TRAMO_3_PORCENTAJE,
                        sobreExceso: constants.RENTA_SEMANAL_TRAMO_3_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_SEMANAL_TRAMO_3_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_SEMANAL_TRAMO_4_DESDE,
                        hasta: constants.RENTA_SEMANAL_TRAMO_4_HASTA,
                        porcentaje: constants.RENTA_SEMANAL_TRAMO_4_PORCENTAJE,
                        sobreExceso: constants.RENTA_SEMANAL_TRAMO_4_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_SEMANAL_TRAMO_4_CUOTA_FIJA
                    }
                ];
                break;
            default: // mensual
                tramos = [
                    {
                        desde: constants.RENTA_MENSUAL_TRAMO_1_DESDE,
                        hasta: constants.RENTA_MENSUAL_TRAMO_1_HASTA,
                        porcentaje: constants.RENTA_MENSUAL_TRAMO_1_PORCENTAJE,
                        sobreExceso: constants.RENTA_MENSUAL_TRAMO_1_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_MENSUAL_TRAMO_1_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_MENSUAL_TRAMO_2_DESDE,
                        hasta: constants.RENTA_MENSUAL_TRAMO_2_HASTA,
                        porcentaje: constants.RENTA_MENSUAL_TRAMO_2_PORCENTAJE,
                        sobreExceso: constants.RENTA_MENSUAL_TRAMO_2_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_MENSUAL_TRAMO_2_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_MENSUAL_TRAMO_3_DESDE,
                        hasta: constants.RENTA_MENSUAL_TRAMO_3_HASTA,
                        porcentaje: constants.RENTA_MENSUAL_TRAMO_3_PORCENTAJE,
                        sobreExceso: constants.RENTA_MENSUAL_TRAMO_3_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_MENSUAL_TRAMO_3_CUOTA_FIJA
                    },
                    {
                        desde: constants.RENTA_MENSUAL_TRAMO_4_DESDE,
                        hasta: constants.RENTA_MENSUAL_TRAMO_4_HASTA,
                        porcentaje: constants.RENTA_MENSUAL_TRAMO_4_PORCENTAJE,
                        sobreExceso: constants.RENTA_MENSUAL_TRAMO_4_SOBRE_EXCESO,
                        cuotaFija: constants.RENTA_MENSUAL_TRAMO_4_CUOTA_FIJA
                    }
                ];
                break;
        }
    
        // Buscar tramo correspondiente
        for (const tramo of tramos) {
            if (salarioGravado >= tramo.desde && salarioGravado <= tramo.hasta) {
                const exceso = Math.max(0, salarioGravado - tramo.sobreExceso);
                const retencion = tramo.cuotaFija + (exceso * tramo.porcentaje);
                return Math.round(retencion * 100) / 100;
            }
        }
    
        return 0;
    }


    
    private static getDefaultCalculation() {
        return {
            totalIngresos: 0,
            isssEmpleado: 0,
            isssPatronal: 0,
            afpEmpleado: 0,
            afpPatronal: 0,
            salarioGravado: 0,
            renta: 0,
            totalDescuentos: 0,
            sueldoNeto: 0
        };
    }

    /**
     * Obtiene información detallada del tramo aplicado
     */
    static obtenerInformacionTramo(salarioGravado: number, tipoPlanilla: string = 'mensual') {
        const tramos = this.getTramosRenta(tipoPlanilla);
        salarioGravado = Math.round(salarioGravado * 100) / 100;
        
        let tramoAplicado = null;
        let numeroTramo = 0;

        if (salarioGravado >= tramos.TRAMO_1?.DESDE && salarioGravado <= tramos.TRAMO_1?.HASTA) {
            tramoAplicado = tramos.TRAMO_1;
            numeroTramo = 1;
        } else if (salarioGravado >= tramos.TRAMO_2?.DESDE && salarioGravado <= tramos.TRAMO_2?.HASTA) {
            tramoAplicado = tramos.TRAMO_2;
            numeroTramo = 2;
        } else if (salarioGravado >= tramos.TRAMO_3?.DESDE && salarioGravado <= tramos.TRAMO_3?.HASTA) {
            tramoAplicado = tramos.TRAMO_3;
            numeroTramo = 3;
        } else if (tramos.TRAMO_4) {
            tramoAplicado = tramos.TRAMO_4;
            numeroTramo = 4;
        }

        if (!tramoAplicado) {
            return null;
        }

        return {
            tramo_numero: numeroTramo,
            desde: tramoAplicado.DESDE,
            hasta: tramoAplicado.HASTA,
            porcentaje: tramoAplicado.PORCENTAJE * 100, // Convertir a porcentaje
            sobre_exceso: tramoAplicado.SOBRE_EXCESO || 0,
            cuota_fija: tramoAplicado.CUOTA_FIJA || 0,
            exceso: Math.max(0, salarioGravado - (tramoAplicado.SOBRE_EXCESO || 0)),
            retencion_calculada: this.calcularRetencionRenta(salarioGravado, tipoPlanilla)
        };
    }

    /**
     * Calcula el recálculo de renta para junio o diciembre
     */
    static calcularRecalculoRenta(
        salarioAcumulado: number, 
        tipoRecalculo: string = 'junio', 
        retencionesAnteriores: number = 0
    ): number {
        const tramos = this.RECALCULOS?.[tipoRecalculo.toUpperCase()] || {};
        salarioAcumulado = Math.round(salarioAcumulado * 100) / 100;

        if (salarioAcumulado <= 0) {
            return 0.00;
        }

        let retencionTotal = 0;

        // Buscar el tramo correspondiente
        if (salarioAcumulado >= tramos.TRAMO_1?.DESDE && salarioAcumulado <= tramos.TRAMO_1?.HASTA) {
            retencionTotal = 0; // Sin retención
        } else if (salarioAcumulado >= tramos.TRAMO_2?.DESDE && salarioAcumulado <= tramos.TRAMO_2?.HASTA) {
            const exceso = salarioAcumulado - tramos.TRAMO_2.SOBRE_EXCESO;
            retencionTotal = tramos.TRAMO_2.CUOTA_FIJA + (exceso * tramos.TRAMO_2.PORCENTAJE);
        } else if (salarioAcumulado >= tramos.TRAMO_3?.DESDE && salarioAcumulado <= tramos.TRAMO_3?.HASTA) {
            const exceso = salarioAcumulado - tramos.TRAMO_3.SOBRE_EXCESO;
            retencionTotal = tramos.TRAMO_3.CUOTA_FIJA + (exceso * tramos.TRAMO_3.PORCENTAJE);
        } else if (tramos.TRAMO_4) {
            const exceso = salarioAcumulado - tramos.TRAMO_4.SOBRE_EXCESO;
            retencionTotal = tramos.TRAMO_4.CUOTA_FIJA + (exceso * tramos.TRAMO_4.PORCENTAJE);
        }

        // Restar las retenciones ya efectuadas
        const retencionAdicional = retencionTotal - retencionesAnteriores;
        return Math.round(Math.max(0, retencionAdicional) * 100) / 100;
    }

    /**
     * Determina si un empleado califica para la deducción de $1,600 anuales
     */
    static calificaDeduccionEmpleadoAsalariado(salarioAnual: number): boolean {
        return salarioAnual <= 9100.00;
    }

    // ==================== MÉTODOS LEGACY (mantener compatibilidad) ====================

    static calcularDescuentoISSSEmpleado(salario: number): number {
        return Math.round(Math.min(salario, 1000) * this.DESCUENTOS.ISSS_EMPLEADO * 100) / 100;
    }

    static calcularDescuentoISSSPatronal(salario: number): number {
        return Math.round(Math.min(salario, 1000) * this.DESCUENTOS.ISSS_PATRONO * 100) / 100;
    }

    static calcularDescuentoAFPEmpleado(salario: number): number {
        return Math.round(salario * this.DESCUENTOS.AFP_EMPLEADO * 100) / 100;
    }

    static calcularDescuentoAFPPatronal(salario: number): number {
        return Math.round(salario * this.DESCUENTOS.AFP_PATRONO * 100) / 100;
    }

    /**
     * Método legacy - mantener para compatibilidad
     * @deprecated Use calcularRetencionRenta() con las nuevas tablas 2025
     */
    static calcularRenta(salarioNeto: number): number {
        // console.warn('⚠️ Método calcularRenta() es legacy. Use calcularRetencionRenta() con las nuevas tablas 2025');
        
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

    // ==================== MÉTODOS DE UTILIDAD ====================

    /**
     * Método para verificar si las constantes están cargadas
     */
    static isLoaded(): boolean {
        return !!this.constants;
    }

    /**
     * Método para actualizar constantes si es necesario
     */
    static updateConstants(constants: any) {
        if (constants?.planilla?.original) {
            localStorage.setItem('SP_constants', JSON.stringify(constants));
        }
    }

    /**
     * Método para obtener la versión del decreto aplicado
     */
    static getVersionDecreto(): string {
        return 'Decreto No. 10 - Abril 2025';
    }

    /**
     * Método para verificar si se debe aplicar recálculo
     */
    static debeAplicarRecalculo(mes: number): string | null {
        if (mes === 6) return 'junio';
        if (mes === 12) return 'diciembre';
        return null;
    }

    /**
     * Método para obtener información completa de cálculo
     */
    static obtenerInformacionCompletaCalculo(
        salarioDevengado: number,
        montoHorasExtra: number,
        comisiones: number,
        bonificaciones: number,
        otrosIngresos: number,
        tipoPlanilla: string = 'mensual'
    ) {
        const calculos = this.calcularDescuentosEmpleado(
            salarioDevengado, montoHorasExtra, comisiones, bonificaciones, otrosIngresos, tipoPlanilla
        );
        
        const informacionTramo = this.obtenerInformacionTramo(calculos.salarioGravado, tipoPlanilla);
        
        return {
            ...calculos,
            tramo_aplicado: informacionTramo,
            tipo_planilla: tipoPlanilla,
            decreto_aplicado: this.getVersionDecreto(),
            califica_deduccion_asalariado: this.calificaDeduccionEmpleadoAsalariado(
                this.extrapolarSalarioAnual(calculos.totalIngresos, tipoPlanilla)
            )
        };
    }
}