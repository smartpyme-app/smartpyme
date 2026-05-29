import { Component, EventEmitter, Input, OnInit, Output } from '@angular/core';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';

@Component({
  selector: 'app-editar-abono',
  templateUrl: './editar-abono.component.html'
})
export class EditarAbonoComponent implements OnInit {

  @Input() abono: any = {};
  @Input() endpoint = 'venta/abono';
  @Output() saved = new EventEmitter<void>();

  public formaPagos: any[] = [];
  public bancos: any[] = [];
  public saving = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService
  ) {}

  ngOnInit(): void {
    this.apiService.getAll('formas-de-pago/list').subscribe(formaPagos => {
      this.formaPagos = formaPagos;
    }, error => { this.alertService.error(error); });

    if (this.apiService.isModuloBancos()) {
      this.apiService.getAll('banco/cuentas/list').subscribe(bancos => {
        this.bancos = bancos;
      }, error => { this.alertService.error(error); });
    } else {
      this.apiService.getAll('bancos/list').subscribe(bancos => {
        this.bancos = bancos;
      }, error => { this.alertService.error(error); });
    }
  }

  public requiereBanco(): boolean {
    const fp = this.abono?.forma_pago;
    if (!fp || fp === 'Efectivo') {
      return false;
    }
    if (this.endpoint === 'compra/abono') {
      return true;
    }
    return fp !== 'Wompi';
  }

  public cambioMetodoDePago(): void {
    if (!this.requiereBanco()) {
      this.abono.detalle_banco = '';
      return;
    }
    if (this.apiService.isModuloBancos()) {
      const formaPagoSeleccionada = this.formaPagos.find((fp: any) => fp.nombre === this.abono.forma_pago);
      this.abono.detalle_banco = formaPagoSeleccionada?.banco?.nombre_banco || '';
    }
  }

  public onSubmit(): void {
    this.saving = true;
    this.apiService.store(this.endpoint, this.abono).subscribe(() => {
      this.saving = false;
      this.alertService.success('Abono actualizado', 'El abono fue actualizado exitosamente.');
      this.saved.emit();
    }, error => {
      this.alertService.error(error);
      this.saving = false;
    });
  }
}
