import { Component, EventEmitter, Input, OnInit, Output, OnChanges, SimpleChanges } from '@angular/core';
import { CommonModule } from '@angular/common';
import { BoxfulApiService } from '@services/boxful/boxful-api.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-boxful-shipment-details',
  templateUrl: './boxful-shipment-details.component.html',
  styleUrls: ['./boxful-shipment-details.component.css'],
  standalone: true,
  imports: [CommonModule],
})
export class BoxfulShipmentDetailsComponent implements OnInit, OnChanges {
  @Input() shipmentId!: string;
  @Output() cerrar = new EventEmitter<void>();

  cargando = false;
  detallesEnvio: any = null;

  constructor(
    private boxfulApiService: BoxfulApiService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    if (this.shipmentId) {
      this.cargarDetalles();
    }
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes['shipmentId'] && !changes['shipmentId'].firstChange && this.shipmentId) {
      this.cargarDetalles();
    }
  }

  cargarDetalles(): void {
    this.detallesEnvio = null;
    this.cargando = true;
    this.boxfulApiService.getShipment(this.shipmentId).subscribe({
      next: (res) => {
        this.detallesEnvio = res;
        this.cargando = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.cargando = false;
        this.cerrarModal();
      }
    });
  }

  getShipmentDetails(): any {
    if (!this.detallesEnvio) {
      return null;
    }
    return this.detallesEnvio.shipment || this.detallesEnvio.shipmentData || this.detallesEnvio;
  }

  cerrarModal(): void {
    this.cerrar.emit();
  }
}
