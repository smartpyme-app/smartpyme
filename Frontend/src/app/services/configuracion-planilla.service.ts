import { Injectable } from '@angular/core';
import { HttpClient, HttpParams } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { AlertService } from './alert.service';
import { ApiService } from './api.service';

export interface ConceptoPlanilla {
  nombre: string;
  codigo: string;
  tipo: 'porcentaje' | 'monto_fijo' | 'tabla_progresiva' | 'sistema_existente' | 'escala_antiguedad' | 'dias_fijos';
  valor?: number;
  tope_maximo?: number;
  base_calculo: string;
  es_deduccion: boolean;
  es_patronal: boolean;
  aplica_renta?: boolean;
  obligatorio: boolean;
  orden?: number;
  tabla?: TramoTabla[];
  escala?: EscalaAntiguedad[];
  dias?: number;
}

export interface TramoTabla {
  desde: number;
  hasta: number;
  porcentaje: number;
  cuota_fija: number;
}

export interface EscalaAntiguedad {
  desde_años: number;
  hasta_años: number;
  dias: number;
}

export interface ConfiguracionPlanilla {
  id: number;
  empresa_id: number;
  cod_pais: string;
  configuracion: {
    conceptos: { [codigo: string]: ConceptoPlanilla };
    configuraciones_generales: {
      moneda: string;
      dias_mes: number;
      horas_dia: number;
      recargo_horas_extra: number;
      frecuencia_pago_predeterminada: string;
      salario_minimo: number;
    };
  };
  activo: boolean;
  fecha_vigencia_desde: string;
  fecha_vigencia_hasta?: string;
}

export interface PlantillaPais {
  nombre: string;
  configuracion: any;
}

export interface TipoConcepto {
  nombre: string;
  descripcion: string;
  campos_requeridos: string[];
  campos_opcionales?: string[];
}

export interface ResultadoCalculo {
  datos_entrada: any;
  tipo_planilla: string;
  resultados: any;
}

@Injectable({
  providedIn: 'root'
})
export class ConfiguracionPlanillaService {

private readonly API_URL =  'planillas/configuracion-planilla';

  constructor(
    private apiService: ApiService, 
    private alertService: AlertService
  ) {}

  // ==========================================
  // MÉTODOS PRINCIPALES
  // ==========================================

  /**
   * Obtener configuración actual de la empresa
   */
  obtenerConfiguracion(): Observable<ConfiguracionPlanilla> {
    return this.apiService.getAll(this.API_URL).pipe(
      map(response => {
        if (response.success) {
          return response.data;
        } else {
          throw new Error(response.message || 'Error al obtener configuración');
        }
      }),
      catchError(error => {
        this.alertService.error('Error al cargar la configuración de planilla');
        throw error;
      })
    );
  }

  /**
   * Actualizar configuración de planilla
   */
  actualizarConfiguracion(configuracion: any, codPais?: string, fechaVigencia?: string): Observable<any> {
    const payload: any = {
      configuracion: configuracion
    };

    if (codPais) {
      payload.cod_pais = codPais;
    }

    if (fechaVigencia) {
      payload.fecha_vigencia_desde = fechaVigencia;
    }

    return this.apiService.store(this.API_URL, payload).pipe(
      map(response => {
        if (response.success) {
          this.alertService.success("Exito",'Configuración actualizada exitosamente');
          return response.data;
        } else {
          throw new Error(response.message || 'Error al actualizar configuración');
        }
      }),
      catchError(error => {
        const errorMsg = error.error?.message || 'Error al actualizar la configuración';
        this.alertService.error(errorMsg);
        throw error;
      })
    );
  }

  /**
   * Obtener plantillas de configuración por país
   */
  obtenerPlantillas(): Observable<{ [cod: string]: PlantillaPais }> {
    return this.apiService.getAll(this.API_URL + '/plantillas').pipe(
      map(response => {
        if (response.success) {
          return response.data;
        } else {
          throw new Error(response.message || 'Error al obtener plantillas');
        }
      })
    );
  }

  /**
   * Obtener tipos de conceptos disponibles
   */
  obtenerTiposConceptos(): Observable<{ tipos_conceptos: any, bases_calculo: any }> {
    return this.apiService.getAll(this.API_URL + '/tipos-conceptos').pipe(
      map(response => {
        if (response.success) {
          return response.data;
        } else {
          throw new Error(response.message || 'Error al obtener tipos de conceptos');
        }
      })
    );
  }

  /**
   * Probar cálculo con configuración actual
   */
  probarCalculo(datosEmpleado: any): Observable<ResultadoCalculo> {
    return this.apiService.store(this.API_URL + '/probar', datosEmpleado).pipe(
      map(response => {
        if (response.success) {
          return response.data;
        } else {
          throw new Error(response.message || 'Error al probar cálculo');
        }
      }),
      catchError(error => {
        this.alertService.error('Error al probar el cálculo');
        throw error;
      })
    );
  }

  /**
   * Obtener historial de configuraciones
   */
  obtenerHistorial(): Observable<any[]> {
    return this.apiService.getAll(this.API_URL + '/historial').pipe(
      map(response => {
        if (response.success) {
          return response.data;
        } else {
          throw new Error(response.message || 'Error al obtener historial');
        }
      })
    );
  }

  // ==========================================
  // MÉTODOS UTILITARIOS
  // ==========================================

  /**
   * Crear concepto vacío con valores por defecto
   */
  crearConceptoVacio(tipo: string = 'porcentaje'): ConceptoPlanilla {
    const conceptoBase: ConceptoPlanilla = {
      nombre: '',
      codigo: '',
      tipo: tipo as any,
      base_calculo: 'salario_devengado',
      es_deduccion: false,
      es_patronal: false,
      obligatorio: false,
      orden: 99
    };

    switch (tipo) {
      case 'porcentaje':
        conceptoBase.valor = 0;
        break;
      case 'monto_fijo':
        conceptoBase.valor = 0;
        break;
      case 'tabla_progresiva':
        conceptoBase.tabla = [
          { desde: 0, hasta: 1000, porcentaje: 0, cuota_fija: 0 }
        ];
        break;
      case 'escala_antiguedad':
        conceptoBase.escala = [
          { desde_años: 1, hasta_años: 3, dias: 15 }
        ];
        break;
      case 'dias_fijos':
        conceptoBase.dias = 15;
        break;
    }

    return conceptoBase;
  }

  /**
   * Validar estructura de concepto
   */
  validarConcepto(concepto: ConceptoPlanilla): { valido: boolean; errores: string[] } {
    const errores: string[] = [];

    if (!concepto.nombre?.trim()) {
      errores.push('El nombre es requerido');
    }

    if (!concepto.codigo?.trim()) {
      errores.push('El código es requerido');
    }

    if (!concepto.base_calculo?.trim()) {
      errores.push('La base de cálculo es requerida');
    }

    switch (concepto.tipo) {
      case 'porcentaje':
        if (concepto.valor === undefined || concepto.valor < 0) {
          errores.push('El porcentaje debe ser mayor o igual a 0');
        }
        break;
      
      case 'monto_fijo':
        if (concepto.valor === undefined || concepto.valor < 0) {
          errores.push('El monto debe ser mayor o igual a 0');
        }
        break;
      
      case 'tabla_progresiva':
        if (!concepto.tabla || concepto.tabla.length === 0) {
          errores.push('Debe definir al menos un tramo en la tabla');
        }
        break;
      
      case 'dias_fijos':
        if (concepto.dias === undefined || concepto.dias <= 0) {
          errores.push('Los días deben ser mayor a 0');
        }
        break;
    }

    return {
      valido: errores.length === 0,
      errores
    };
  }

  /**
   * Validar configuración completa
   */
  validarConfiguracion(configuracion: any): { valida: boolean; errores: string[] } {
    const errores: string[] = [];

    if (!configuracion.conceptos || Object.keys(configuracion.conceptos).length === 0) {
      errores.push('Debe definir al menos un concepto');
    }

    // Validar cada concepto
    for (const [codigo, concepto] of Object.entries(configuracion.conceptos || {})) {
      const validacion = this.validarConcepto(concepto as ConceptoPlanilla);
      if (!validacion.valido) {
        errores.push(`Concepto ${codigo}: ${validacion.errores.join(', ')}`);
      }
    }

    // Validar códigos únicos
    const codigos = Object.values(configuracion.conceptos || {}).map((c: any) => c.codigo);
    const codigosUnicos = new Set(codigos);
    if (codigos.length !== codigosUnicos.size) {
      errores.push('Los códigos de conceptos deben ser únicos');
    }

    return {
      valida: errores.length === 0,
      errores
    };
  }

  /**
   * Aplicar plantilla de país
   */
  aplicarPlantillaPais(codPais: string): Observable<any> {
    return this.obtenerPlantillas().pipe(
      map(plantillas => {
        const plantilla = plantillas[codPais];
        if (!plantilla) {
          throw new Error(`No se encontró plantilla para el país: ${codPais}`);
        }
        return plantilla.configuracion;
      })
    );
  }

  /**
   * Formatear configuración para mostrar
   */
  formatearConfiguracionParaMostrar(configuracion: ConfiguracionPlanilla): any {
    const conceptos = configuracion.configuracion.conceptos || {};
    const conceptosArray = Object.entries(conceptos).map(([codigoKey, concepto]) => ({
      codigo_key: codigoKey,
      ...concepto
    }));


    // Ordenar por orden si existe
    conceptosArray.sort((a, b) => (a.orden || 999) - (b.orden || 999));

    return {
      ...configuracion,
      conceptos_array: conceptosArray,
      total_conceptos: conceptosArray.length,
      conceptos_deducciones: conceptosArray.filter(c => c.es_deduccion).length,
      conceptos_ingresos: conceptosArray.filter(c => !c.es_deduccion).length
    };
  }

  /**
   * Exportar configuración a JSON
   */
  exportarConfiguracion(configuracion: ConfiguracionPlanilla): void {
    const dataStr = JSON.stringify(configuracion.configuracion, null, 2);
    const dataUri = 'data:application/json;charset=utf-8,' + encodeURIComponent(dataStr);
    
    const exportFileDefaultName = `configuracion-planilla-${configuracion.cod_pais}-${new Date().getTime()}.json`;
    
    const linkElement = document.createElement('a');
    linkElement.setAttribute('href', dataUri);
    linkElement.setAttribute('download', exportFileDefaultName);
    linkElement.click();
  }

  /**
   * Importar configuración desde JSON
   */
  importarConfiguracion(file: File): Observable<any> {
    return new Observable(observer => {
      const reader = new FileReader();
      
      reader.onload = (e) => {
        try {
          const configuracion = JSON.parse(e.target?.result as string);
          
          const validacion = this.validarConfiguracion(configuracion);
          if (!validacion.valida) {
            observer.error(new Error('Configuración inválida: ' + validacion.errores.join(', ')));
            return;
          }
          
          observer.next(configuracion);
          observer.complete();
        } catch (error) {
          observer.error(new Error('Error al leer el archivo JSON'));
        }
      };
      
      reader.onerror = () => {
        observer.error(new Error('Error al leer el archivo'));
      };
      
      reader.readAsText(file);
    });
  }

  /**
   * Duplicar concepto
   */
  duplicarConcepto(concepto: ConceptoPlanilla, nuevoSufijo: string = '_copia'): ConceptoPlanilla {
    return {
      ...concepto,
      codigo: concepto.codigo + nuevoSufijo,
      nombre: concepto.nombre + ' (Copia)',
      orden: (concepto.orden || 0) + 1
    };
  }

  /**
   * Obtener conceptos por tipo
   */
  obtenerConceptosPorTipo(configuracion: ConfiguracionPlanilla): any {
    const conceptos = configuracion.configuracion.conceptos || {};
    
    return {
      deducciones: Object.fromEntries(
        Object.entries(conceptos).filter(([_, concepto]) => (concepto as any).es_deduccion)
      ),
      ingresos: Object.fromEntries(
        Object.entries(conceptos).filter(([_, concepto]) => !(concepto as any).es_deduccion)
      ),
      obligatorios: Object.fromEntries(
        Object.entries(conceptos).filter(([_, concepto]) => (concepto as any).obligatorio)
      ),
      opcionales: Object.fromEntries(
        Object.entries(conceptos).filter(([_, concepto]) => !(concepto as any).obligatorio)
      )
    };
  }
}