import { Component, OnInit } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { RouterModule } from '@angular/router';
import { ConstantsService } from '@services/constants.service';
import { PlanillaConstants } from './../../constants/planilla.constants';

@Component({
    selector: 'app-test-constants',
    template: `
    <div class="container mt-4">
      <h3>Test de Constantes - Planilla y Empleados</h3>
      
      <div class="row">
        <div class="col-md-6">
          <h4>Estado de Carga</h4>
          <p><strong>Constantes cargadas:</strong> {{ constantsLoaded ? 'Sí' : 'No' }}</p>
          <p><strong>Planilla Constants:</strong> {{ planillaConstantsAvailable ? 'Sí' : 'No' }}</p>
          <p><strong>Empleados Constants:</strong> {{ empleadosConstantsAvailable ? 'Sí' : 'No' }}</p>
        </div>
        
        <div class="col-md-6">
          <h4>Acciones</h4>
          <button class="btn btn-primary me-2" (click)="loadConstants()">Cargar Constantes</button>
          <button class="btn btn-secondary" (click)="checkConstants()">Verificar Constantes</button>
        </div>
      </div>

      @if (constantsData) {
        <div class="row mt-4">
          <div class="col-md-6">
            <h4>Estados de Planilla</h4>
            <ul>
              @for (estado of planillaEstados; track estado.key) {
                <li>{{ estado.key }}: {{ estado.value }}</li>
              }
            </ul>
          </div>
          
          <div class="col-md-6">
            <h4>Estados de Empleado</h4>
            <ul>
              @for (estado of empleadoEstados; track estado.key) {
                <li>{{ estado.key }}: {{ estado.value }}</li>
              }
            </ul>
          </div>
        </div>
      }

      @if (constantsData) {
        <div class="row mt-4">
          <div class="col-md-6">
            <h4>Tipos de Contrato</h4>
            <ul>
              @for (tipo of tiposContrato; track tipo.key) {
                <li>{{ tipo.key }}: {{ tipo.value }}</li>
              }
            </ul>
          </div>
          
          <div class="col-md-6">
            <h4>Tipos de Jornada</h4>
            <ul>
              @for (tipo of tiposJornada; track tipo.key) {
                <li>{{ tipo.key }}: {{ tipo.value }}</li>
              }
            </ul>
          </div>
        </div>
      }

      @if (message) {
        <div class="alert alert-info mt-4">
          {{ message }}
        </div>
      }
    </div>
  `,
    standalone: true,
    imports: [CommonModule, RouterModule, FormsModule]
})
export class TestConstantsComponent implements OnInit {
  constantsLoaded = false;
  planillaConstantsAvailable = false;
  empleadosConstantsAvailable = false;
  constantsData: any = null;
  planillaEstados: any[] = [];
  empleadoEstados: any[] = [];
  tiposContrato: any[] = [];
  tiposJornada: any[] = [];
  message = '';

  constructor(private constantsService: ConstantsService) {}

  ngOnInit() {
    this.checkConstants();
  }

  loadConstants() {
    this.message = 'Cargando constantes...';
    this.constantsService.loadConstants().subscribe(
      (constants) => {
        this.constantsData = constants;
        this.constantsLoaded = true;
        this.message = 'Constantes cargadas exitosamente';
        this.checkConstants();
      },
      (error) => {
        this.message = 'Error cargando constantes: ' + error.message;
        console.error('Error loading constants:', error);
      }
    );
  }

  checkConstants() {
    // Verificar si las constantes están en localStorage
    const spConstants = localStorage.getItem('SP_constants');
    const planillaConstants = localStorage.getItem('PLANILLA_CONSTANTS');
    
    this.constantsLoaded = !!(spConstants || planillaConstants);
    
    if (spConstants) {
      const constants = JSON.parse(spConstants);
      this.constantsData = constants;
      
      // Verificar constantes de planilla
      if (constants.planilla) {
        this.planillaConstantsAvailable = true;
        this.planillaEstados = this.objectToArray(constants.planilla.original?.ESTADOS_PLANILLA || {});
        this.empleadoEstados = this.objectToArray(constants.planilla.original?.ESTADOS_EMPLEADO || {});
        this.tiposContrato = this.objectToArray(constants.planilla.original?.TIPOS_CONTRATO || {});
        this.tiposJornada = this.objectToArray(constants.planilla.original?.TIPOS_JORNADA || {});
      }
      
      // Verificar constantes de empleados (que están dentro de planilla)
      this.empleadosConstantsAvailable = !!(constants.planilla?.original?.ESTADOS_EMPLEADO);
    }
    
    this.message = `Verificación completada. Constantes cargadas: ${this.constantsLoaded}, Planilla: ${this.planillaConstantsAvailable}, Empleados: ${this.empleadosConstantsAvailable}`;
  }

  private objectToArray(obj: any): any[] {
    return Object.entries(obj).map(([key, value]) => ({ key, value }));
  }
}
