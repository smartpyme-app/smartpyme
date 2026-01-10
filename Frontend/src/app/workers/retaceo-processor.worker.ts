/// <reference lib="webworker" />

/**
 * Web Worker para procesamiento pesado de datos de retaceo
 * Evita bloquear el hilo principal de la UI
 */

interface ProcessMessage {
  type: 'PROCESS_GASTOS' | 'PROCESS_DISTRIBUCION';
  gastos?: any[];
  distribucion?: any[];
  id?: string;
}

addEventListener('message', ({ data }: { data: ProcessMessage }) => {
  const { type, gastos, distribucion, id } = data;

  try {
    let result: any = null;

    switch (type) {
      case 'PROCESS_GASTOS':
        if (!gastos) {
          postMessage({ id, result: null, error: 'Datos incompletos' });
          return;
        }
        result = processGastos(gastos);
        break;

      case 'PROCESS_DISTRIBUCION':
        if (!distribucion) {
          postMessage({ id, result: null, error: 'Datos incompletos' });
          return;
        }
        result = processDistribucion(distribucion);
        break;

      default:
        postMessage({ id, result: null, error: 'Tipo de procesamiento no válido' });
        return;
    }

    postMessage({ id, result, error: null });
  } catch (error: any) {
    postMessage({ id, result: null, error: error.message || 'Error desconocido' });
  }
});

function processGastos(gastos: any[]): any {
  const gastosMap: any = {};

  // Agrupar gastos por tipo
  gastos.forEach((gasto: any) => {
    const tipo = gasto.tipo_gasto;

    if (!gastosMap[tipo]) {
      gastosMap[tipo] = {
        lista: [],
        seleccionados: []
      };
    }

    const gastoObj = {
      id: gasto.id,
      id_retaceo: gasto.id_retaceo,
      id_gasto: gasto.id_gasto,
      tipo_gasto: tipo,
      monto: parseFloat(gasto.monto || 0)
    };

    gastosMap[tipo].lista.push(gastoObj);
    gastosMap[tipo].seleccionados.push(gasto.id_gasto);
  });

  return gastosMap;
}

function processDistribucion(distribucion: any[]): any {
  return distribucion.map(item => ({
    ...item,
    cantidad: parseFloat(item.cantidad || 0),
    costo_original: parseFloat(item.costo_original || 0),
    valor_fob: parseFloat(item.valor_fob || 0),
    porcentaje_distribucion: parseFloat(item.porcentaje_distribucion || 0),
    porcentaje_dai: parseFloat(item.porcentaje_dai || 0),
    monto_transporte: parseFloat(item.monto_transporte || 0),
    monto_seguro: parseFloat(item.monto_seguro || 0),
    monto_dai: parseFloat(item.monto_dai || 0),
    monto_otros: parseFloat(item.monto_otros || 0),
    costo_landed: parseFloat(item.costo_landed || 0),
    costo_retaceado: parseFloat(item.costo_retaceado || 0),
  }));
}

