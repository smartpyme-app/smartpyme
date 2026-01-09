import { Component, OnInit, TemplateRef, DestroyRef, inject, ChangeDetectionStrategy, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule, ReactiveFormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { FormBuilder, FormGroup, Validators, FormArray } from '@angular/forms';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { 
  ConfiguracionPlanillaService, 
  ConfiguracionPlanilla, 
  ConceptoPlanilla,
  PlantillaPais 
} from '../../../services/configuracion-planilla.service';
import { AlertService } from '../../../services/alert.service';
import { ApiService } from '../../../services/api.service';
import { subscriptionHelper } from '@shared/utils/subscription.helper';

@Component({
    selector: 'app-configuracion-planilla',
    templateUrl: './configuracion-planilla.component.html',
    standalone: true,
    imports: [CommonModule, FormsModule, ReactiveFormsModule, RouterModule],
    changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ConfiguracionPlanillaComponent implements OnInit {

  // ==========================================
  // PROPIEDADES PRINCIPALES
  // ==========================================

  configuracion: ConfiguracionPlanilla | null = null;
  configuracionForm: FormGroup;
  conceptoForm: FormGroup;
  
  loading = true;
  saving = false;
  probandoCalculo = false;

  // Modales
  modalRef: BsModalRef | null = null;
  modalConceptoRef: BsModalRef | null = null;
  
  // Estados
  modoEdicion = false;
  conceptoEditando: ConceptoPlanilla | null = null;
  indiceConceptoEditando = -1;

  // Datos para formularios
  tiposConceptos: any = {};
  basesCalculo: any = {};
  plantillasPaises: { [cod: string]: PlantillaPais } = {};
  
  // Pestañas activas
  tabActiva = 'conceptos';
  
  // Resultados de prueba
  resultadoPrueba: any = null;

  private destroyRef = inject(DestroyRef);
  private untilDestroyed = subscriptionHelper(this.destroyRef);

  // ==========================================
  // CONSTRUCTOR
  // ==========================================

  constructor(
    private fb: FormBuilder,
    private configService: ConfiguracionPlanillaService,
    private alertService: AlertService,
    private modalService: BsModalService,
    public apiService: ApiService,
    private cdr: ChangeDetectorRef
  ) {
    this.configuracionForm = this.createConfiguracionForm();
    this.conceptoForm = this.createConceptoForm();
  }

  // ==========================================
  // LIFECYCLE
  // ==========================================

  ngOnInit(): void {
    this.cargarDatosIniciales();
  }

  // ==========================================
  // INICIALIZACIÓN
  // ==========================================

  private cargarDatosIniciales(): void {
    this.loading = true;
    
  this.configService.obtenerConfiguracion().pipe(this.untilDestroyed()).subscribe({
    next: (response: any) => {
      
      this.configuracion = response;
      
      this.poblarFormulario();
      this.loading = false;
      this.cdr.markForCheck();
    },
    error: (error) => {
      console.error('Error cargando configuración:', error);
      this.loading = false;
      this.cdr.markForCheck();
    }
  });
    
    // Cargar tipos de conceptos
  this.configService.obtenerTiposConceptos().pipe(this.untilDestroyed()).subscribe({
    next: (response: any) => {
      const data = response.data || response;
      this.tiposConceptos = data.tipos_conceptos;
      this.basesCalculo = data.bases_calculo;
      this.cdr.markForCheck();
    },
    error: (error) => {
      console.error('Error cargando tipos de conceptos:', error);
      this.cdr.markForCheck();
    }
  });
  
    // Cargar plantillas de paísea
    this.configService.obtenerPlantillas().pipe(this.untilDestroyed()).subscribe({
      next: (response: any) => {
        // ✅ MANEJAR AMBOS CASOS
        const data = response.data || response;
        this.plantillasPaises = data;
        this.cdr.markForCheck();

      },
      error: (error) => {
        console.error('Error cargando plantillas:', error);
        this.cdr.markForCheck();
      }
    });
  }

  private createConfiguracionForm(): FormGroup {
    return this.fb.group({
      cod_pais: ['SV', Validators.required],
      conceptos: this.fb.array([]),
      configuraciones_generales: this.fb.group({
        moneda: ['USD', Validators.required],
        dias_mes: [30, [Validators.required, Validators.min(1)]],
        horas_dia: [8, [Validators.required, Validators.min(1)]],
        recargo_horas_extra: [25, [Validators.required, Validators.min(0)]],
        frecuencia_pago_predeterminada: ['quincenal'],
        salario_minimo: [365, [Validators.required, Validators.min(0)]]
      })
    });
  }

  private createConceptoForm(): FormGroup {
    return this.fb.group({
      nombre: ['', Validators.required],
      codigo: ['', Validators.required],
      tipo: ['porcentaje', Validators.required],
      valor: [0],
      tope_maximo: [null],
      base_calculo: ['salario_devengado', Validators.required],
      es_deduccion: [false],
      es_patronal: [false],
      aplica_renta: [false],
      obligatorio: [false],
      orden: [1],
      tabla: this.fb.array([]),
      escala: this.fb.array([]),
      dias: [null]
    });
  }

  private poblarFormulario(): void {
    if (!this.configuracion) return;

    const config = this.configuracion.configuracion;

    // Poblar datos generales
    // Si cod_pais es null, usar 'SV' por defecto
    const codPais = this.configuracion.cod_pais || 'SV';

    this.configuracionForm.patchValue({
      cod_pais: codPais,
      configuraciones_generales: config.configuraciones_generales
    });

    // Poblar conceptos
    this.poblarConceptos(config.conceptos || {});
  }

  private poblarConceptos(conceptos: { [codigo: string]: ConceptoPlanilla }): void {
    const conceptosArray = this.configuracionForm.get('conceptos') as FormArray;
    conceptosArray.clear();

    Object.entries(conceptos).forEach(([codigo, concepto]) => {
      conceptosArray.push(this.createConceptoFormGroup(concepto));
    });
  }

  private createConceptoFormGroup(concepto: ConceptoPlanilla): FormGroup {
    return this.fb.group({
      nombre: [concepto.nombre, Validators.required],
      codigo: [concepto.codigo, Validators.required],
      tipo: [concepto.tipo, Validators.required],
      valor: [concepto.valor],
      tope_maximo: [concepto.tope_maximo],
      base_calculo: [concepto.base_calculo, Validators.required],
      es_deduccion: [concepto.es_deduccion],
      es_patronal: [concepto.es_patronal],
      aplica_renta: [concepto.aplica_renta],
      obligatorio: [concepto.obligatorio],
      orden: [concepto.orden || 1],
      tabla: [concepto.tabla || []],
      escala: [concepto.escala || []],
      dias: [concepto.dias]
    });
  }

  // ==========================================
  // GETTERS
  // ==========================================

  get conceptosFormArray(): FormArray {
    return this.configuracionForm.get('conceptos') as FormArray;
  }

  get conceptosArray(): ConceptoPlanilla[] {
    if (!this.configuracion) return [];
    const conceptos = this.configuracion.configuracion.conceptos || {};
    return Object.values(conceptos);
  }

  get conceptosIngresos(): ConceptoPlanilla[] {
    return this.conceptosDevengos; // Alias para compatibilidad
  }

  get conceptosDevengos(): ConceptoPlanilla[] {
    return this.conceptosArray?.filter(c => !c.es_deduccion && !c.es_patronal) || [];
  }
  
  get conceptosDeducciones(): ConceptoPlanilla[] {
    return this.conceptosArray?.filter(c => c.es_deduccion && !c.es_patronal) || [];
  }
  
  get conceptosPatronales(): ConceptoPlanilla[] {
    return this.conceptosArray?.filter(c => c.es_patronal) || [];
  }

  // ==========================================
  // ACCIONES PRINCIPALES
  // ==========================================

  guardarConfiguracion(): void {
    if (this.configuracionForm.invalid) {
      this.alertService.error('Por favor complete todos los campos requeridos');
      return;
    }

    this.saving = true;
    const formValue = this.configuracionForm.value;
    
    // Convertir array de conceptos a objeto con códigos como keys
    const conceptosObj: { [codigo: string]: ConceptoPlanilla } = {};
    formValue.conceptos.forEach((concepto: ConceptoPlanilla) => {
      conceptosObj[concepto.codigo] = concepto;
    });

    const configuracionFinal = {
      conceptos: conceptosObj,
      configuraciones_generales: formValue.configuraciones_generales
    };

    this.configService.actualizarConfiguracion(
      configuracionFinal, 
      formValue.cod_pais
    ).pipe(this.untilDestroyed()).subscribe({
      next: (response) => {
        this.alertService.success('success','Configuración guardada exitosamente');
        this.cargarDatosIniciales(); // Recargar datos
        this.modoEdicion = false;
        this.saving = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error guardando configuración:', error);
        this.saving = false;
        this.cdr.markForCheck();
      }
    });
  }

  cancelarEdicion(): void {
    this.modoEdicion = false;
    this.poblarFormulario(); // Restaurar valores originales
    this.cdr.markForCheck();
  }

  abrirModalConcepto(template: TemplateRef<any>, concepto?: ConceptoPlanilla, indice?: number): void {
    if (concepto) {
      // ✅ ARREGLAR: Buscar el índice correcto en el FormArray
      const conceptosFormArray = this.conceptosFormArray;
      let indiceReal = -1;
      
      // Buscar el índice real del concepto en el FormArray
      for (let i = 0; i < conceptosFormArray.length; i++) {
        if (conceptosFormArray.at(i).get('codigo')?.value === concepto.codigo) {
          indiceReal = i;
          break;
        }
      }
      
      this.conceptoEditando = { ...concepto };
      this.indiceConceptoEditando = indiceReal;
      this.conceptoForm.patchValue(concepto);
    } else {
      // Nuevo concepto
      this.conceptoEditando = null;
      this.indiceConceptoEditando = -1;
      this.conceptoForm.reset();
      this.conceptoForm.patchValue({
        tipo: 'porcentaje',
        base_calculo: 'salario_devengado',
        es_deduccion: false,
        es_patronal: false,
        obligatorio: false,
        orden: this.conceptosArray.length + 1
      });
    }
  
    this.modalConceptoRef = this.modalService.show(template, { class: 'modal-lg' });
  }

guardarConcepto(): void {
  if (this.conceptoForm.invalid) {
    this.alertService.error('Por favor complete todos los campos del concepto');
    return;
  }

    const conceptoValue = this.conceptoForm.value;
    
    // Validar código único
    const codigoExiste = this.conceptoEditando 
      ? (conceptoValue.codigo !== this.conceptoEditando.codigo && 
        this.conceptosArray.some(c => c.codigo === conceptoValue.codigo))
      : this.conceptosArray.some(c => c.codigo === conceptoValue.codigo);

    if (codigoExiste) {
      this.alertService.error('El código del concepto ya existe');
      return;
    }

    if (this.conceptoEditando) {
      // Actualizar concepto existente
      this.conceptosFormArray.at(this.indiceConceptoEditando).patchValue(conceptoValue);
    } else {
      // Agregar nuevo concepto
      this.conceptosFormArray.push(this.createConceptoFormGroup(conceptoValue));
    }

    this.cerrarModalConcepto();
    
    // ✅ GUARDAR AUTOMÁTICAMENTE
    this.guardarConfiguracionAutomatico();
  }

  eliminarConcepto(concepto: ConceptoPlanilla): void {
    // ✅ Buscar el índice real en el FormArray por código
    const conceptosFormArray = this.conceptosFormArray;
    let indiceReal = -1;
    
    for (let i = 0; i < conceptosFormArray.length; i++) {
      if (conceptosFormArray.at(i).get('codigo')?.value === concepto.codigo) {
        indiceReal = i;
        break;
      }
    }
    
    if (indiceReal === -1) {
      this.alertService.error('No se pudo encontrar el concepto a eliminar');
      return;
    }
  
    if (confirm(`¿Está seguro de eliminar el concepto "${concepto.nombre}"?`)) {
      this.conceptosFormArray.removeAt(indiceReal);
      
      // Guardar automáticamente
      this.guardarConfiguracionAutomatico();
    }
  }

  eliminarConceptoPorCodigo(codigo: string): void {
    const conceptosFormArray = this.conceptosFormArray;
    let indiceReal = -1;
    let nombreConcepto = '';
    
    for (let i = 0; i < conceptosFormArray.length; i++) {
      const conceptoForm = conceptosFormArray.at(i);
      if (conceptoForm.get('codigo')?.value === codigo) {
        indiceReal = i;
        nombreConcepto = conceptoForm.get('nombre')?.value || codigo;
        break;
      }
    }
    
    if (indiceReal === -1) {
      this.alertService.error('No se pudo encontrar el concepto a eliminar');
      return;
    }
  
    if (confirm(`¿Está seguro de eliminar el concepto "${nombreConcepto}"?`)) {
      this.conceptosFormArray.removeAt(indiceReal);
      this.guardarConfiguracionAutomatico();
    }
  }

  duplicarConcepto(concepto: ConceptoPlanilla): void {
    const conceptoDuplicado = this.configService.duplicarConcepto(concepto);
    this.conceptosFormArray.push(this.createConceptoFormGroup(conceptoDuplicado));
    
    // ✅ GUARDAR AUTOMÁTICAMENTE
    this.guardarConfiguracionAutomatico();
  }


  cerrarModalConcepto(): void {
    this.modalConceptoRef?.hide();
    this.conceptoEditando = null;
    this.indiceConceptoEditando = -1;
  }

  // ==========================================
  // PLANTILLAS Y PAÍSES
  // ==========================================

  aplicarPlantillaPais(codPais: string): void {
    if (!confirm(`¿Desea aplicar la plantilla de ${this.plantillasPaises[codPais]?.nombre}? Esto reemplazará la configuración actual.`)) {
      return;
    }

    this.configService.aplicarPlantillaPais(codPais).pipe(this.untilDestroyed()).subscribe({
      next: (response: any) => {
        const configuracion = response.data;
        // Aplicar la nueva configuración al formulario
        this.configuracionForm.patchValue({
          cod_pais: codPais,
          configuraciones_generales: configuracion.configuraciones_generales
        });
        
        this.poblarConceptos(configuracion.conceptos || {});
        this.modoEdicion = true;
        this.alertService.success(`Plantilla de ${this.plantillasPaises[codPais]?.nombre} aplicada`, 'success');
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error aplicando plantilla:', error);
        this.cdr.markForCheck();
      }
    });
  }

  // ==========================================
  // PRUEBAS DE CÁLCULO
  // ==========================================

  probarCalculo(): void {
    const datosEmpleado = {
      salario_base: 800,
      dias_laborados: 30,
      tipo_planilla: 'mensual',
      horas_extra: 0,
      monto_horas_extra: 0,
      comisiones: 0,
      bonificaciones: 0,
      otros_ingresos: 0,
      prestamos: 0,
      anticipos: 0,
      otros_descuentos: 0,
      descuentos_judiciales: 0
    };

    this.probandoCalculo = true;
    
    this.configService.probarCalculo(datosEmpleado).pipe(this.untilDestroyed()).subscribe({
      next: (response: any) => {
        this.resultadoPrueba = response.data;
        this.probandoCalculo = false;
        this.tabActiva = 'prueba';
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error probando cálculo:', error);
        this.probandoCalculo = false;
        this.cdr.markForCheck();
      }
    });
  }

  // ==========================================
  // IMPORT/EXPORT
  // ==========================================

  exportarConfiguracion(): void {
    if (this.configuracion) {
      this.configService.exportarConfiguracion(this.configuracion);
    }
  }

  onArchivoSeleccionado(event: any): void {
    const file = event.target.files[0];
    if (!file) return;

    this.configService.importarConfiguracion(file).pipe(this.untilDestroyed()).subscribe({
      next: (configuracion) => {
        // Aplicar configuración importada
        this.configuracionForm.patchValue({
          configuraciones_generales: configuracion.configuraciones_generales
        });
        
        this.poblarConceptos(configuracion.conceptos || {});
        this.modoEdicion = true;
        this.alertService.success('Configuración importada exitosamente', 'success');
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error importando configuración:', error);
        this.cdr.markForCheck();
      }
    });

    // Limpiar input
    event.target.value = '';
  }

  // ==========================================
  // UTILIDADES
  // ==========================================

  cambiarTab(tab: string): void {
    this.tabActiva = tab;
    this.cdr.markForCheck();
  }

  onTipoConceptoCambiado(): void {
    const tipo = this.conceptoForm.get('tipo')?.value;
    
    // Limpiar campos específicos del tipo anterior
    this.conceptoForm.patchValue({
      valor: null,
      tabla: [],
      escala: [],
      dias: null
    });

    // Configurar valores por defecto según el tipo
    switch (tipo) {
      case 'porcentaje':
        this.conceptoForm.patchValue({ valor: 0 });
        break;
      case 'monto_fijo':
        this.conceptoForm.patchValue({ valor: 0 });
        break;
      case 'dias_fijos':
        this.conceptoForm.patchValue({ dias: 15 });
        break;
    }
  }

  formatearMonto(monto: number): string {
    return new Intl.NumberFormat('es-SV', {
      style: 'currency',
      currency: 'USD',
      minimumFractionDigits: 2
    }).format(monto);
  }

  formatearPorcentaje(porcentaje: number): string {
    return `${porcentaje}%`;
  }

  obtenerNombrePais(codPais: string): string {
    return this.plantillasPaises[codPais]?.nombre || codPais;
  }

  // ==========================================
  // VALIDACIONES
  // ==========================================

  validarConfiguracion(): { valida: boolean; errores: string[] } {
    const formValue = this.configuracionForm.value;
    
    // Convertir conceptos para validación
    const conceptosObj: { [codigo: string]: ConceptoPlanilla } = {};
    formValue.conceptos.forEach((concepto: ConceptoPlanilla) => {
      conceptosObj[concepto.codigo] = concepto;
    });

    const configuracion = {
      conceptos: conceptosObj,
      configuraciones_generales: formValue.configuraciones_generales
    };

    return this.configService.validarConfiguracion(configuracion);
  }

  private guardarConfiguracionAutomatico(): void {
    if (this.configuracionForm.invalid) {
      this.alertService.error('Error en la configuración');
      return;
    }
  
    this.saving = true;
    const formValue = this.configuracionForm.value;
    
    // Convertir array de conceptos a objeto
    const conceptosObj: { [codigo: string]: ConceptoPlanilla } = {};
    formValue.conceptos.forEach((concepto: ConceptoPlanilla) => {
      conceptosObj[concepto.codigo] = concepto;
    });
  
    const configuracionFinal = {
      conceptos: conceptosObj,
      configuraciones_generales: formValue.configuraciones_generales
    };
  
    this.configService.actualizarConfiguracion(
      configuracionFinal, 
      formValue.cod_pais
    ).pipe(this.untilDestroyed()).subscribe({
      next: (response) => {
        // ✅ NOTIFICACIÓN DISCRETA
        this.alertService.success('success','Cambios guardados automáticamente');
        
        // Recargar configuración
        this.cargarConfiguracionSinLoading();
        this.saving = false;
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error guardando automáticamente:', error);
        this.alertService.error('Error al guardar cambios');
        this.saving = false;
        this.cdr.markForCheck();
      }
    });
  }

  private cargarConfiguracionSinLoading(): void {
    this.configService.obtenerConfiguracion().pipe(this.untilDestroyed()).subscribe({
      next: (response: any) => {
        this.configuracion = response;
        this.poblarFormulario();
        this.cdr.markForCheck();
      },
      error: (error) => {
        console.error('Error recargando configuración:', error);
        this.cdr.markForCheck();
      }
    });
  }
}