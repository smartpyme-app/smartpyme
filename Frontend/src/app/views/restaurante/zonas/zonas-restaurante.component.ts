import { Component, OnInit, TemplateRef } from '@angular/core';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { RestauranteService, ZonaRestaurante } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-zonas-restaurante',
  templateUrl: './zonas-restaurante.component.html',
  styleUrls: ['./zonas-restaurante.component.css'],
  standalone: false,
})
export class ZonasRestauranteComponent implements OnInit {
  zonas: ZonaRestaurante[] = [];
  zona: Partial<ZonaRestaurante> = {};
  loading = false;
  guardando = false;
  modalRef?: BsModalRef;

  constructor(
    private restauranteService: RestauranteService,
    private alertService: AlertService,
    private modalService: BsModalService
  ) {}

  ngOnInit(): void {
    this.cargar();
  }

  cargar(): void {
    this.loading = true;
    this.restauranteService.getZonas().subscribe({
      next: (z) => {
        this.zonas = z || [];
        this.loading = false;
      },
      error: (err) => {
        this.alertService.error(err);
        this.loading = false;
      }
    });
  }

  openModal(template: TemplateRef<any>, zona: Partial<ZonaRestaurante>): void {
    this.zona = zona.id
      ? { ...zona }
      : { nombre: '', orden: 0, activo: true };
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
  }

  closeModal(): void {
    this.modalRef?.hide();
    this.alertService.modal = false;
  }

  onSubmit(): void {
    if (!this.zona.nombre?.trim()) {
      this.alertService.warning('Nombre requerido', 'Indique el nombre de la zona.');
      return;
    }
    this.guardando = true;
    const payload = {
      nombre: this.zona.nombre.trim(),
      orden: this.zona.orden ?? 0,
      activo: this.zona.activo !== false
    };
    const obs = this.zona.id
      ? this.restauranteService.actualizarZona(this.zona.id, payload)
      : this.restauranteService.crearZona(payload);
    obs.subscribe({
      next: () => {
        this.guardando = false;
        this.closeModal();
        this.cargar();
        this.alertService.success('Zona guardada', '');
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardando = false;
      }
    });
  }

  setEstado(z: ZonaRestaurante): void {
    this.restauranteService.actualizarZona(z.id, { activo: z.activo }).subscribe({
      next: () => this.alertService.success('Estado actualizado', ''),
      error: (err) => {
        this.alertService.error(err);
        this.cargar();
      }
    });
  }

  eliminar(z: ZonaRestaurante): void {
    if (!confirm(`¿Eliminar la zona «${z.nombre}»?`)) {
      return;
    }
    this.restauranteService.eliminarZona(z.id).subscribe({
      next: () => {
        this.cargar();
        this.alertService.success('Zona eliminada', '');
      },
      error: (err) => this.alertService.error(err)
    });
  }
}
