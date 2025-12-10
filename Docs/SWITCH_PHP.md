# Guía para Cambiar Versión de PHP con Laravel Herd

Este documento explica cómo cambiar entre diferentes versiones de PHP usando Laravel Herd en el proyecto SmartPyme.

## Requisitos Previos

- Laravel Herd instalado en tu Mac
- PHP 7.4 y PHP 8.4 instalados a través de Herd

## Método 1: Usando el Script Automatizado (Recomendado)

### Paso 1: Dar permisos de ejecución (solo la primera vez)

```bash
chmod +x switch-php.sh
```

### Paso 2: Ejecutar el script

**Para cambiar a PHP 7.4:**
```bash
./switch-php.sh 7.4
```

**Para cambiar a PHP 8.4:**
```bash
./switch-php.sh 8.4
```

**Para ver la versión actual:**
```bash
./switch-php.sh
```

### Ejemplo de salida:

```
Cambiando versión de PHP...
Versión actual: 8.4.15
Versión solicitada: 7.4
✓ Versión cambiada exitosamente

Verificando nueva versión:
PHP 7.4.33 (cli) (built: Dec 13 2023 21:52:05) ( NTS )
✓ Confirmado: PHP 7.4 está activo

Listo! Puedes continuar trabajando con PHP 7.4
```

## Método 2: Comando Manual de Herd

Si prefieres usar el comando directamente:

**Para PHP 7.4:**
```bash
herd use php@7.4
```

**Para PHP 8.4:**
```bash
herd use php@8.4
```

**Verificar versión actual:**
```bash
php -v
```

## Verificación

Después de cambiar la versión, siempre verifica que el cambio fue exitoso:

```bash
php -v
```

Deberías ver algo como:
```
PHP 7.4.33 (cli) (built: Dec 13 2023 21:52:05) ( NTS )
```

o

```
PHP 8.4.15 (cli) (built: Nov 20 2025 14:28:49) (NTS clang 15.0.0)
```

## Notas Importantes

1. **El cambio es global**: Cuando cambias la versión de PHP con Herd, afecta a toda tu máquina, no solo al proyecto actual.

2. **Reiniciar servidores**: Si tienes servidores de desarrollo corriendo (como `php artisan serve`), es recomendable reiniciarlos después de cambiar la versión.

3. **Composer**: Después de cambiar la versión de PHP, es buena práctica ejecutar:
   ```bash
   composer install
   ```
   Para asegurarte de que todas las dependencias sean compatibles con la nueva versión.

4. **Versiones disponibles**: Para ver todas las versiones de PHP disponibles en Herd:
   ```bash
   brew list | grep php
   ```

## Solución de Problemas

### Error: "herd: command not found"
- Asegúrate de que Laravel Herd esté instalado
- Verifica que Herd esté en tu PATH

### Error: "Could not open input file: list"
- Este es un mensaje normal de Herd, puedes ignorarlo
- El cambio de versión debería funcionar de todas formas

### La versión no cambia
- Cierra y vuelve a abrir tu terminal
- Verifica que Herd esté funcionando correctamente: `herd --version`

## Uso en el Proyecto SmartPyme

- **PHP 7.4**: Usado para Laravel 8 (branch actual)
- **PHP 8.4**: Usado para versiones más recientes de Laravel

Recuerda cambiar a la versión correcta antes de trabajar en cada branch.

