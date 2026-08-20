import { Component, EventEmitter, Input, Output } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { NgSelectModule } from '@ng-select/ng-select';
import { TranslatePipe } from '@ngx-translate/core';
import { CurrencyPipe } from '@pipes/currency-format.pipe';
import { CrearProyectoComponent } from '@shared/modals/crear-proyecto/crear-proyecto.component';
import { FacturacionV2Component } from '../../facturacion-tienda-v2/facturacion-v2.component';

@Component({
  selector: 'app-pos-opciones-avanzadas',
  standalone: true,
  imports: [
    CommonModule,
    FormsModule,
    NgSelectModule,
    TranslatePipe,
    CrearProyectoComponent,
    CurrencyPipe,
  ],
  templateUrl: './pos-opciones-avanzadas.component.html',
  styleUrls: ['./pos-opciones-avanzadas.component.css'],
})
export class PosOpcionesAvanzadasComponent {
  @Input({ required: true }) host!: FacturacionV2Component;
  @Output() aplicar = new EventEmitter<void>();
}
