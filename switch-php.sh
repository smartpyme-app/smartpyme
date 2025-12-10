#!/bin/bash

# Script para cambiar entre versiones de PHP usando Laravel Herd
# Uso: ./switch-php.sh [7.4|8.4]

# Colores para output
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Función para mostrar ayuda
show_help() {
    echo -e "${YELLOW}Script para cambiar versión de PHP usando Laravel Herd${NC}"
    echo ""
    echo "Uso:"
    echo "  ./switch-php.sh [7.4|8.4]"
    echo ""
    echo "Ejemplos:"
    echo "  ./switch-php.sh 7.4    # Cambia a PHP 7.4"
    echo "  ./switch-php.sh 8.4    # Cambia a PHP 8.4"
    echo ""
    echo "Sin argumentos, muestra la versión actual de PHP"
}

# Verificar si Herd está instalado
if ! command -v herd &> /dev/null; then
    echo -e "${RED}Error: Laravel Herd no está instalado o no está en el PATH${NC}"
    exit 1
fi

# Si no se proporciona argumento, mostrar versión actual
if [ $# -eq 0 ]; then
    echo -e "${YELLOW}Versión actual de PHP:${NC}"
    php -v | head -n 1
    echo ""
    echo "Para cambiar de versión, ejecuta:"
    echo "  ./switch-php.sh 7.4  o  ./switch-php.sh 8.4"
    exit 0
fi

# Obtener la versión solicitada
VERSION=$1

# Validar que la versión sea válida
if [ "$VERSION" != "7.4" ] && [ "$VERSION" != "8.4" ]; then
    echo -e "${RED}Error: Versión inválida. Solo se permiten 7.4 o 8.4${NC}"
    show_help
    exit 1
fi

# Obtener versión actual antes del cambio
CURRENT_VERSION=$(php -v | head -n 1 | grep -oP '\d+\.\d+' | head -n 1)

echo -e "${YELLOW}Cambiando versión de PHP...${NC}"
echo -e "Versión actual: ${CURRENT_VERSION}"
echo -e "Versión solicitada: ${VERSION}"

# Cambiar versión usando Herd
if herd use php@${VERSION} 2>&1 | grep -q "Herd is now using"; then
    echo -e "${GREEN}✓ Versión cambiada exitosamente${NC}"
else
    echo -e "${RED}Error al cambiar la versión${NC}"
    exit 1
fi

# Verificar la nueva versión
echo ""
echo -e "${YELLOW}Verificando nueva versión:${NC}"
NEW_VERSION=$(php -v | head -n 1)
echo -e "${GREEN}${NEW_VERSION}${NC}"

# Verificar que la versión sea la correcta
VERIFIED_VERSION=$(php -v | head -n 1 | grep -oP '\d+\.\d+' | head -n 1)
if [ "$VERIFIED_VERSION" = "$VERSION" ]; then
    echo -e "${GREEN}✓ Confirmado: PHP ${VERSION} está activo${NC}"
else
    echo -e "${YELLOW}⚠ Advertencia: La versión verificada (${VERIFIED_VERSION}) no coincide exactamente con la solicitada (${VERSION})${NC}"
fi

echo ""
echo -e "${GREEN}Listo! Puedes continuar trabajando con PHP ${VERSION}${NC}"


