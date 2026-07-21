import {
  limpiarAsignacionLotesDetalle,
  limpiarLotesSiCambioCantidad,
} from './lotes-venta.util';

describe('lotes-venta.util', () => {
  function detalleConLotes(): any {
    return {
      inventario_por_lotes: true,
      lotes_asignados: [{ lote_id: 1, cantidad: 2 }],
      lote_id: 1,
      lote: { id: 1 },
    };
  }

  it('limpiarAsignacionLotesDetalle limpia lote_id y lotes_asignados', () => {
    const detalle = detalleConLotes();
    limpiarAsignacionLotesDetalle(detalle);
    expect(detalle.lotes_asignados).toBeNull();
    expect(detalle.lote_id).toBeNull();
    expect(detalle.lote).toBeNull();
  });

  it('limpiarLotesSiCambioCantidad limpia en metodología Manual', () => {
    const detalle = detalleConLotes();
    limpiarLotesSiCambioCantidad(detalle, {
      skipLimpiarLotes: false,
      metodologiaManual: true,
    });
    expect(detalle.lotes_asignados).toBeNull();
  });

  it('limpiarLotesSiCambioCantidad no limpia con skipLimpiarLotes', () => {
    const detalle = detalleConLotes();
    limpiarLotesSiCambioCantidad(detalle, {
      skipLimpiarLotes: true,
      metodologiaManual: true,
    });
    expect(detalle.lotes_asignados).toEqual([{ lote_id: 1, cantidad: 2 }]);
  });

  it('limpiarLotesSiCambioCantidad no limpia si no es Manual', () => {
    const detalle = detalleConLotes();
    limpiarLotesSiCambioCantidad(detalle, {
      skipLimpiarLotes: false,
      metodologiaManual: false,
    });
    expect(detalle.lotes_asignados).toEqual([{ lote_id: 1, cantidad: 2 }]);
  });
});
