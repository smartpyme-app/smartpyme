import {
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  inject,
  OnInit,
  TemplateRef,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { BsModalService, BsModalRef } from 'ngx-bootstrap/modal';
import { PantallaRestaurante, RestauranteService } from '@services/restaurante.service';
import { AlertService } from '@services/alert.service';

@Component({
  selector: 'app-pantallas-restaurante',
  templateUrl: './pantallas-restaurante.component.html',
  standalone: false,
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PantallasRestauranteComponent implements OnInit {
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  pantallas: PantallaRestaurante[] = [];
  pantalla: Partial<PantallaRestaurante> = {};
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
    this.cdr.markForCheck();
    this.restauranteService
      .getPantallas()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (filas) => {
          this.pantallas = filas || [];
          this.loading = false;
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.alertService.error(err);
          this.loading = false;
          this.cdr.markForCheck();
        },
      });
  }

  openModal(template: TemplateRef<unknown>, pantalla: Partial<PantallaRestaurante>): void {
    this.pantalla = pantalla.id ? { ...pantalla } : { nombre: '', orden: 0, activo: true };
    this.alertService.modal = true;
    this.modalRef = this.modalService.show(template, { class: 'modal-md', backdrop: 'static' });
    this.cdr.markForCheck();
  }

  closeModal(): void {
    this.modalRef?.hide();
    this.alertService.modal = false;
  }

  onSubmit(): void {
    if (!this.pantalla.nombre?.trim()) {
      this.alertService.warning('Nombre requerido', 'Indique el nombre de la pantalla.');
      return;
    }
    this.guardando = true;
    this.cdr.markForCheck();
    const req = this.pantalla.id
      ? this.restauranteService.actualizarPantalla(this.pantalla.id, this.pantalla)
      : this.restauranteService.crearPantalla(this.pantalla);
    req.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.guardando = false;
        this.closeModal();
        this.cargar();
      },
      error: (err) => {
        this.alertService.error(err);
        this.guardando = false;
        this.cdr.markForCheck();
      },
    });
  }

  eliminar(pantalla: PantallaRestaurante): void {
    if (!confirm(`¿Eliminar la pantalla ${pantalla.nombre}?`)) {
      return;
    }
    this.restauranteService
      .eliminarPantalla(pantalla.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.cargar(),
        error: (err) => this.alertService.error(err),
      });
  }
}
