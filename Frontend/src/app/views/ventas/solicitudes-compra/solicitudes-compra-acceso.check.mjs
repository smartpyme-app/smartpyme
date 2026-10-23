/** ponytail: menú Licencias org. solo padre con licencia y subítems visibles */
function mostrarMenuLicenciasOrganizacion(empresa, hasPermission) {
  if (!empresa?.es_empresa_padre || !empresa.licencia) return false;
  return (
    hasPermission('organizacion.empresas.ver')
    || hasPermission('organizacion.usuarios.ver')
  );
}

const perms = new Set(['organizacion.empresas.ver']);
const has = (p) => perms.has(p);

if (mostrarMenuLicenciasOrganizacion({ licencia: {}, es_empresa_hija: true }, has)) {
  throw new Error('hija con licencia no debe ver menú Licencias');
}
if (mostrarMenuLicenciasOrganizacion({ es_empresa_padre: true }, has)) {
  throw new Error('padre sin licencia no debe ver menú aunque tenga permiso');
}
if (!mostrarMenuLicenciasOrganizacion({ es_empresa_padre: true, licencia: {} }, has)) {
  throw new Error('padre con licencia y permiso debe ver menú');
}
if (mostrarMenuLicenciasOrganizacion({ es_empresa_padre: true, licencia: {} }, () => false)) {
  throw new Error('padre con licencia sin permisos no debe ver menú');
}
const soloLicenciasVer = new Set(['organizacion.licencias.ver']);
if (mostrarMenuLicenciasOrganizacion({ es_empresa_padre: true, licencia: {} }, (p) => soloLicenciasVer.has(p))) {
  throw new Error('organizacion.licencias.ver solo no debe mostrar menú vacío');
}

console.log('solicitudes-compra-acceso.check.mjs OK');
