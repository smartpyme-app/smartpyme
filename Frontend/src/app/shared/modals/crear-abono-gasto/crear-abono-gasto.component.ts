import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { AlertService } from '@services/alert.service';
import { ApiService } from '@services/api.service';
import { NotificacionesContainerComponent } from '@shared/parts/notificaciones/notificaciones-container.component';

@Component({
  selector: 'app-crear-abono-gasto',
  templateUrl: './crear-abono-gasto.component.html',
  standalone: true,
  imports: [CommonModule, FormsModule, NotificacionesContainerComponent],
})
export class CrearAbonoGastoComponent implements OnInit {

  @Input() gasto: any = {};
  @Output() update = new EventEmitter();
  public formaPagos: any[] = [];
  public bancos: any[] = [];
  public abono: any = {};
  public saving = false;

  constructor(
    public apiService: ApiService,
    private alertService: AlertService
  ) { }

  ngOnInit() {
    this.abono.total = this.gasto.saldo;
    this.abono.fecha = this.apiService.date();
    this.abono.id_gasto = this.gasto.id;
    this.abono.nombre_de = this.gasto.nombre_proveedor;
    this.abono.estado = 'Confirmado';
    this.abono.forma_pago = 'Efectivo';
    this.abono.detalle_banco = '';
    this.abono.id_sucursal = this.apiService.auth_user().id_sucursal;
    this.abono.id_empresa = this.apiService.auth_user().id_empresa;
    this.abono.id_usuario = this.apiService.auth_user().id;

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
    return !!fp && fp !== 'Efectivo' && fp !== 'Wompi';
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

  public setTotal(total: any) {
    this.abono.total = total;
    document.getElementById('total')!.focus();
  }

  public onSubmit() {
    this.saving = true;

    if (this.abono.total >= this.gasto.total) {
      this.abono.concepto = 'Pago total';
    } else {
      this.abono.concepto = 'Abono';
    }

    this.apiService.store('gasto/abono', this.abono).subscribe(() => {
      this.alertService.modal = false;
      this.update.emit();
      this.saving = false;
    }, error => { this.alertService.error(error); this.saving = false; });
  }
}
