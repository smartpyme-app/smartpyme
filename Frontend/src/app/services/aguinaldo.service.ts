import { Injectable } from '@angular/core';
import { Observable } from 'rxjs';
import { map, catchError } from 'rxjs/operators';
import { ApiService } from './api.service';
import { AlertService } from './alert.service';

export interface Aguinaldo {
  id: number;
  id_empresa: number;
  id_sucursal: number;
  anio: number;
  total_aguinaldos: number;
  total_retenciones: number;
  estado: number;
  observaciones?: string;
  created_at?: string;
  updated_at?: string;
}

export interface AguinaldoDetalle {
  id: number;
  id_aguinaldo: number;
  id_empleado: number;
  monto_aguinaldo_bruto: number;
  monto_exento: number;
  monto_gravado: number;
  retencion_renta: number;
  aguinaldo_neto: number;
  notas?: string;
  nombres?: string;
  apellidos?: string;
  codigo?: string;
  dui?: string;
}

export interface SugerenciaAguinaldo {
  sugerencia: number;
  meses_trabajados: number;
  salario_base: number;
  fecha_ingreso: string;
  tipo_contrato: number;
}

export interface PreviewCalculo {
  monto_bruto: number;
  monto_exento: number;
  monto_gravado: number;
  retencion_renta: number;
  aguinaldo_neto: number;
}

@Injectable({
  providedIn: 'root'
})
export class AguinaldoService {
  private readonly API_URL = 'aguinaldos';

  constructor(
    private apiService: ApiService,
    private alertService: AlertService
  ) {}

  // ==========================================
  // MÉTODOS PRINCIPALES
  // ==========================================

  /**
   * Listar aguinaldos con filtros
   */
  listar(filtros: any = {}): Observable<any> {
    return this.apiService.getAll(this.API_URL, filtros).pipe(
      catchError(error => {
        this.alertService.error('Error al listar aguinaldos');
        throw error;
      })
    );
  }

  /**
   * Obtener un aguinaldo por ID
   */
  obtener(id: number): Observable<any> {
    return this.apiService.read(this.API_URL + '/', id).pipe(
      catchError(error => {
        this.alertService.error('Error al obtener aguinaldo');
        throw error;
      })
    );
  }

  /**
   * Crear un nuevo aguinaldo
   */
  crear(aguinaldo: { anio: number; observaciones?: string }): Observable<any> {
    return this.apiService.store(this.API_URL, aguinaldo).pipe(
      catchError(error => {
        this.alertService.error('Error al crear aguinaldo');
        throw error;
      })
    );
  }

  /**
   * Eliminar un aguinaldo
   */
  eliminar(id: number): Observable<any> {
    return this.apiService.delete(this.API_URL + '/', id).pipe(
      catchError(error => {
        this.alertService.error('Error al eliminar aguinaldo');
        throw error;
      })
    );
  }

  // ==========================================
  // MÉTODOS DE DETALLES
  // ==========================================

  /**
   * Agregar empleado al aguinaldo
   */
  agregarEmpleado(
    idAguinaldo: number,
    datos: {
      id_empleado: number;
      monto_aguinaldo_bruto: number;
      notas?: string;
    }
  ): Observable<any> {
    return this.apiService.store(
      `${this.API_URL}/${idAguinaldo}/agregar-empleado`,
      datos
    ).pipe(
      catchError(error => {
        this.alertService.error('Error al agregar empleado');
        throw error;
      })
    );
  }

  /**
   * Actualizar detalle de aguinaldo (monto y recalcular)
   */
  actualizarDetalle(
    idDetalle: number,
    datos: {
      monto_aguinaldo_bruto: number;
      notas?: string;
    }
  ): Observable<any> {
    return this.apiService.update('aguinaldo-detalles/', idDetalle, datos).pipe(
      catchError(error => {
        this.alertService.error('Error al actualizar detalle');
        throw error;
      })
    );
  }

  /**
   * Eliminar empleado del aguinaldo
   */
  eliminarDetalle(idDetalle: number): Observable<any> {
    return this.apiService.delete('aguinaldo-detalles/', idDetalle).pipe(
      catchError(error => {
        this.alertService.error('Error al eliminar empleado');
        throw error;
      })
    );
  }

  // ==========================================
  // MÉTODOS DE CÁLCULO
  // ==========================================

  /**
   * Obtener sugerencia de aguinaldo para un empleado
   */
  obtenerSugerencia(
    idEmpleado: number,
    anio: number
  ): Observable<SugerenciaAguinaldo> {
    return this.apiService.store(`${this.API_URL}/sugerencia`, {
      id_empleado: idEmpleado,
      anio: anio
    }).pipe(
      map(response => response as SugerenciaAguinaldo),
      catchError(error => {
        this.alertService.error('Error al obtener sugerencia');
        throw error;
      })
    );
  }

  /**
   * Calcular preview de aguinaldo (deducciones en tiempo real)
   */
  calcularPreview(
    montoBruto: number,
    anio: number,
    tipoContrato?: number
  ): Observable<PreviewCalculo> {
    return this.apiService.store(`${this.API_URL}/preview`, {
      monto_bruto: montoBruto,
      anio: anio,
      tipo_contrato: tipoContrato || null
    }).pipe(
      map(response => response as PreviewCalculo),
      catchError(error => {
        // No mostrar error en preview, solo loguear
        console.error('Error al calcular preview:', error);
        throw error;
      })
    );
  }

  // ==========================================
  // MÉTODOS DE PROCESAMIENTO
  // ==========================================

  /**
   * Procesar pago del aguinaldo
   */
  procesarPago(idAguinaldo: number): Observable<any> {
    return this.apiService.store(
      `${this.API_URL}/${idAguinaldo}/pagar`,
      {}
    ).pipe(
      catchError(error => {
        this.alertService.error('Error al procesar pago');
        throw error;
      })
    );
  }

  // ==========================================
  // MÉTODOS DE EXPORTACIÓN
  // ==========================================

  /**
   * Exportar aguinaldo a Excel
   */
  exportarExcel(idAguinaldo: number): Observable<Blob> {
    return this.apiService.export(
      `${this.API_URL}/${idAguinaldo}/excel`,
      {}
    ).pipe(
      catchError(error => {
        this.alertService.error('Error al exportar a Excel');
        throw error;
      })
    );
  }

  /**
   * Exportar aguinaldo a PDF
   */
  exportarPDF(idAguinaldo: number): Observable<Blob> {
    return this.apiService.export(
      `${this.API_URL}/${idAguinaldo}/pdf`,
      {}
    ).pipe(
      catchError(error => {
        this.alertService.error('Error al exportar a PDF');
        throw error;
      })
    );
  }
}
