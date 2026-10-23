/** ponytail: menú Licencias org. solo padre */
function mostrarMenuLicenciasOrganizacion(empresa, hasPermission) {
  if (!empresa?.es_empresa_padre) return false;
  return (
    hasPermission('organizacion.empresas.ver')
    || hasPermission('organizacion.usuarios.ver')
    || hasPermission('organizacion.licencias.ver')
  );
}

const perms = new Set(['organizacion.empresas.ver']);
const has = (p) => perms.has(p);

if (mostrarMenuLicenciasOrganizacion({ licencia: {}, es_empresa_hija: true }, has)) {
  throw new Error('hija con licencia no debe ver menú Licencias');
}
if (!mostrarMenuLicenciasOrganizacion({ es_empresa_padre: true }, has)) {
  throw new Error('padre con permiso debe ver menú');
}
if (mostrarMenuLicenciasOrganizacion({ es_empresa_padre: true }, () => false)) {
  throw new Error('padre sin permisos no debe ver menú');
}

console.log('solicitudes-compra-acceso.check.mjs OK');
