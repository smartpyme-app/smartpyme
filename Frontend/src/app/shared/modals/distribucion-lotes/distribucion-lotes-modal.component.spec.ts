import { DistribucionLotesModalComponent } from './distribucion-lotes-modal.component';

describe('DistribucionLotesModalComponent modo entrada (compras)', () => {
  function createComponent(): any {
    const component: any = Object.create(DistribucionLotesModalComponent.prototype);
    component.alertService = { error: jasmine.createSpy('error') };
    component.modalRef = { hide: jasmine.createSpy('hide') };
    component.confirmado = { emit: jasmine.createSpy('emit') };
    component.modoEntrada = true;
    component.detalle = { cantidad: 1, factor_conversion: 1 };
    component.lotes = [
      { id: 1, numero_lote: 'A', stock: 0, stock_unidades: 0, cantidad_asignada: 10 },
      { id: 2, numero_lote: 'B', stock: 0, stock_unidades: 0, cantidad_asignada: 20 },
    ];
    return component;
  }

  it('en modo entrada permite cantidades mayores al stock actual', () => {
    const component = createComponent();

    expect(component.distribucionValida()).toBe(true);
  });

  it('en modo entrada confirma varios lotes aunque el stock sea 0', () => {
    const component = createComponent();

    component.confirmar();

    expect(component.alertService.error).not.toHaveBeenCalled();
    expect(component.detalle.lotes_asignados).toEqual([
      { lote_id: 1, numero_lote: 'A', cantidad: 10 },
      { lote_id: 2, numero_lote: 'B', cantidad: 20 },
    ]);
    expect(component.detalle.lote_id).toBeNull();
    expect(component.detalle.cantidad).toBe(30);
    expect(component.confirmado.emit).toHaveBeenCalledWith(component.detalle);
  });
});
