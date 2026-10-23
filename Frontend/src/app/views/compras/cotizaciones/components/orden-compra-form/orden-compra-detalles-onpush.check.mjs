/** ponytail: falla si se vuelve a mutar compraDTO.detalles sin nueva referencia de compra (OnPush). */
const loadFromApi = (compra) => {
  const detalles = [...(compra.detalles ?? [])];
  const compraDTO = { ...compra, detalles };
  return { detalles, compraDTO };
};

const compra = { id: 1, detalles: [{ id: 10, id_producto: 5, cantidad: 2 }] };
const { detalles, compraDTO } = loadFromApi(compra);

if (detalles.length !== 1) throw new Error('detalles length');
if (compraDTO.detalles === compra.detalles) throw new Error('must clone detalles array');
if (compraDTO === compra) throw new Error('must clone compra object for OnPush input');

console.log('orden-compra-detalles-onpush.check.mjs OK');
