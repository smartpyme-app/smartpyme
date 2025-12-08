# Análisis: Ajustes para Plantillas de Clientes según País

## Resumen Ejecutivo

Actualmente existen dos tipos de plantillas para importar clientes:
- **Específicas para El Salvador**: `clientes-personas-format.xlsx` y `clientes-empresas-format.xlsx`
- **Generales (otros países)**: `clientes-personas-format-general.xlsx` y `clientes-empresas-format-general.xlsx`

El sistema necesita ajustarse para usar automáticamente las plantillas generales cuando la empresa NO es de El Salvador, tanto para descargar plantillas como para importar datos.

---

## 1. Estado Actual del Sistema

### 1.1 Plantillas Existentes
Ubicación: `Backend/public/docs/`

- ✅ `clientes-personas-format.xlsx` (El Salvador)
- ✅ `clientes-empresas-format.xlsx` (El Salvador)
- ✅ `clientes-personas-format-general.xlsx` (General)
- ✅ `clientes-empresas-format-general.xlsx` (General)

### 1.2 Frontend - Descarga de Plantillas

**Archivo**: `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.html`

**Línea 31**: Actualmente usa una lógica fija:
```html
<a href="{{ apiService.baseUrl + '/docs/' + nombre.toLowerCase() + '-format.xlsx'}}" target="_blank">Descargar plantilla</a>
```

**Problema**: Siempre descarga `clientes-personas-format.xlsx` o `clientes-empresas-format.xlsx`, sin considerar el país de la empresa.

### 1.3 Backend - Importación de Datos

**Archivo**: `Backend/app/Http/Controllers/Api/Ventas/Clientes/ClientesController.php`

**Métodos**:
- `importPersonas()` (línea 399): Usa clase `ClientesPersonas`
- `importEmpresas()` (línea 453): Usa clase `ClientesEmpresas`

**Problema**: Las clases de importación tienen validaciones específicas para El Salvador:
- **ClientesPersonas**: Valida formato DUI (12345678-9), busca departamentos/municipios/distritos de El Salvador
- **ClientesEmpresas**: Valida formato NCR (14 dígitos), busca códigos de actividad económica y ubicaciones de El Salvador

### 1.4 Determinación del País

El sistema ya tiene mecanismos para determinar el país:
- `apiService.auth_user().empresa.pais` → "El Salvador" o nombre del país
- `apiService.auth_user().empresa.cod_pais` → "SV" para El Salvador, otros códigos para otros países
- En backend: `Auth::user()->id_empresa` → se puede obtener el país desde la tabla `empresas`

---

## 2. Ajustes Necesarios

### 2.1 Frontend - Componente de Importación

**Archivo**: `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.html`

**Cambio requerido**: Modificar la línea 31 para usar lógica condicional basada en el país de la empresa.

**Lógica propuesta**:
```typescript
// En el componente TypeScript
get plantillaUrl(): string {
  const nombreArchivo = this.nombre.toLowerCase();
  const esElSalvador = this.apiService.auth_user()?.empresa?.pais === 'El Salvador' || 
                       this.apiService.auth_user()?.empresa?.cod_pais === 'SV';
  
  if (nombreArchivo.includes('clientes-personas') || nombreArchivo.includes('clientes-empresas')) {
    const sufijo = esElSalvador ? '-format.xlsx' : '-format-general.xlsx';
    return `${this.apiService.baseUrl}/docs/${nombreArchivo}${sufijo}`;
  }
  
  // Para otros tipos de importación, mantener lógica actual
  return `${this.apiService.baseUrl}/docs/${nombreArchivo}-format.xlsx`;
}
```

**En el HTML**:
```html
<a [href]="plantillaUrl" target="_blank">Descargar plantilla</a>
```

### 2.2 Backend - Clases de Importación

#### Opción A: Crear clases nuevas para plantillas generales

**Archivos a crear**:
- `Backend/app/Imports/ClientesPersonasGeneral.php`
- `Backend/app/Imports/ClientesEmpresasGeneral.php`

**Diferencias clave**:
- **ClientesPersonasGeneral**: 
  - NO validar formato DUI (o hacerlo opcional)
  - NO buscar códigos de departamento/municipio/distrito (guardar como texto)
  - Campo "pais" opcional
  - Campos de ubicación como texto libre

- **ClientesEmpresasGeneral**:
  - NO validar formato NCR (o hacerlo opcional)
  - NO buscar códigos de actividad económica (guardar giro como texto)
  - NO buscar códigos de ubicación (guardar como texto)
  - Campo "pais" opcional

#### Opción B: Modificar clases existentes con lógica condicional (RECOMENDADO)

**Archivos a modificar**:
- `Backend/app/Imports/ClientesPersonas.php`
- `Backend/app/Imports/ClientesEmpresas.php`

**Cambios propuestos**:
1. Agregar propiedad para detectar si es El Salvador
2. Hacer validaciones específicas condicionales
3. Permitir campos de ubicación como texto cuando no es El Salvador

**Ejemplo para ClientesPersonas**:
```php
private $esElSalvador = false;

public function __construct()
{
    $empresa = \App\Models\Admin\Empresa::find(Auth::user()->id_empresa);
    $this->esElSalvador = ($empresa->cod_pais === 'SV' || $empresa->pais === 'El Salvador');
}

// En el método model(), hacer validaciones condicionales:
if ($this->esElSalvador && !empty($duiNormalizado) && !$this->esDuiValido($duiNormalizado)) {
    // Validar DUI solo para El Salvador
}

// En buscarCodigos(), solo buscar si es El Salvador:
if ($this->esElSalvador) {
    // Buscar códigos de departamento/municipio/distrito
} else {
    // Guardar como texto libre
}
```

### 2.3 Backend - Controlador de Importación

**Archivo**: `Backend/app/Http/Controllers/Api/Ventas/Clientes/ClientesController.php`

**Cambios requeridos**:

**Método `importPersonas()`**:
```php
public function importPersonas(Request $request)
{
    $request->validate(['file' => 'required']);
    
    try {
        // Determinar qué clase usar según el país
        $empresa = \App\Models\Admin\Empresa::find(auth()->user()->id_empresa);
        $esElSalvador = ($empresa->cod_pais === 'SV' || $empresa->pais === 'El Salvador');
        
        $import = $esElSalvador 
            ? new ClientesPersonas() 
            : new ClientesPersonasGeneral(); // O usar lógica condicional en la misma clase
        
        Excel::import($import, $request->file);
        // ... resto del código
    }
}
```

**Método `importEmpresas()`**: Similar lógica.

### 2.4 Campos Específicos de El Salvador vs Generales

#### Clientes Personas

**El Salvador**:
- DUI (formato: 12345678-9, validación estricta)
- Departamento/Municipio/Distrito (con códigos MH)
- NIT (formato salvadoreño)

**General**:
- Documento de identidad (texto libre, sin validación de formato)
- País (campo opcional)
- Departamento/Provincia/Estado (texto libre)
- Ciudad/Municipio (texto libre)
- Código postal (opcional)

#### Clientes Empresas

**El Salvador**:
- NCR (14 dígitos, validación estricta)
- Giro (con código de actividad económica MH)
- Departamento/Municipio/Distrito (con códigos MH)

**General**:
- Número de registro/identificación fiscal (texto libre)
- Giro/Actividad económica (texto libre)
- País (campo opcional)
- Departamento/Provincia/Estado (texto libre)
- Ciudad/Municipio (texto libre)
- Código postal (opcional)

---

## 3. Plan de Implementación

### Fase 1: Frontend - Descarga de Plantillas
1. ✅ Modificar `importar-excel.component.ts` para agregar método `plantillaUrl`
2. ✅ Modificar `importar-excel.component.html` para usar el nuevo método
3. ✅ Probar descarga de plantillas para empresas de El Salvador y otros países

### Fase 2: Backend - Clases de Importación
1. ✅ Crear clases `ClientesPersonasGeneral` y `ClientesEmpresasGeneral` O
2. ✅ Modificar clases existentes con lógica condicional (recomendado)
3. ✅ Ajustar validaciones para que sean opcionales cuando no es El Salvador
4. ✅ Permitir campos de ubicación como texto libre

### Fase 3: Backend - Controlador
1. ✅ Modificar `importPersonas()` para detectar país y usar clase apropiada
2. ✅ Modificar `importEmpresas()` para detectar país y usar clase apropiada
3. ✅ Agregar manejo de errores específico para plantillas generales

### Fase 4: Pruebas
1. ✅ Probar importación con plantilla de El Salvador (empresa SV)
2. ✅ Probar importación con plantilla general (empresa no SV)
3. ✅ Verificar que los campos se guarden correctamente según el tipo
4. ✅ Validar que no se rompan importaciones existentes

---

## 4. Consideraciones Importantes

### 4.1 Compatibilidad hacia atrás
- Las empresas de El Salvador deben seguir funcionando igual
- No romper importaciones existentes
- Mantener validaciones estrictas para El Salvador

### 4.2 Base de Datos
- Verificar que los campos de la tabla `clientes` puedan almacenar valores de texto libre
- Los campos `cod_departamento`, `cod_municipio`, `cod_distrito` pueden ser NULL para países no SV
- El campo `pais` debe ser opcional o tener valor por defecto

### 4.3 Validaciones
- **El Salvador**: Mantener validaciones estrictas (DUI, NCR, códigos MH)
- **Otros países**: Validaciones mínimas (campos requeridos, formato de email, etc.)

### 4.4 Mensajes de Error
- Adaptar mensajes de error según el tipo de plantilla
- No mostrar errores sobre DUI/NCR para empresas no salvadoreñas

---

## 5. Archivos a Modificar/Crear

### Frontend
- ✅ `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.ts`
- ✅ `Frontend/src/app/shared/parts/importar-excel/importar-excel.component.html`

### Backend
- ✅ `Backend/app/Http/Controllers/Api/Ventas/Clientes/ClientesController.php`
- ✅ `Backend/app/Imports/ClientesPersonas.php` (modificar o crear General)
- ✅ `Backend/app/Imports/ClientesEmpresas.php` (modificar o crear General)
- ⚠️ `Backend/app/Imports/ClientesPersonasGeneral.php` (crear si se usa Opción A)
- ⚠️ `Backend/app/Imports/ClientesEmpresasGeneral.php` (crear si se usa Opción A)

---

## 6. Preguntas Pendientes

1. **¿Las plantillas generales ya tienen los campos definidos?** 
   - Necesario revisar `clientes-personas-format-general.xlsx` y `clientes-empresas-format-general.xlsx` para confirmar estructura

2. **¿Qué campos son obligatorios en las plantillas generales?**
   - Definir validaciones mínimas para otros países

3. **¿Se debe mantener el campo "distrito" para otros países?**
   - Algunos países no tienen distritos, considerar campo opcional

4. **¿Cómo manejar el campo "pais" en la importación?**
   - ¿Se debe incluir en la plantilla o se toma de la empresa?

---

## 7. Recomendación Final

**Opción Recomendada**: Modificar las clases existentes con lógica condicional (Opción B)

**Razones**:
- Menos duplicación de código
- Más fácil de mantener
- Una sola fuente de verdad para la lógica de importación
- Cambios futuros se aplican a ambos casos

**Implementación sugerida**:
1. Agregar detección de país en el constructor de las clases de importación
2. Hacer validaciones condicionales basadas en `$this->esElSalvador`
3. Permitir campos opcionales cuando no es El Salvador
4. Mantener compatibilidad total con empresas salvadoreñas


