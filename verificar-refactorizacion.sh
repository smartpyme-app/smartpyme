#!/bin/bash

# Script para verificar el progreso de la refactorización
# Uso: ./verificar-refactorizacion.sh

echo "🔍 Verificando progreso de refactorización..."
echo ""

# Colores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar si estamos en el branch correcto
BRANCH=$(git branch --show-current)
if [[ $BRANCH == *"refactor"* ]]; then
    echo -e "${GREEN}✓${NC} Branch correcto: $BRANCH"
else
    echo -e "${YELLOW}⚠${NC} Branch actual: $BRANCH (considera crear branch de refactorización)"
fi
echo ""

# Verificar Services creados
echo "📦 Services de Compras:"
if [ -f "Backend/app/Services/Compras/CompraService.php" ]; then
    echo -e "${GREEN}✓${NC} CompraService.php"
else
    echo -e "${RED}✗${NC} CompraService.php (no existe)"
fi

if [ -f "Backend/app/Services/Compras/ComprasAuthorizationService.php" ]; then
    echo -e "${GREEN}✓${NC} ComprasAuthorizationService.php"
else
    echo -e "${RED}✗${NC} ComprasAuthorizationService.php (no existe)"
fi

if [ -f "Backend/app/Services/Compras/OrdenCompraService.php" ]; then
    echo -e "${GREEN}✓${NC} OrdenCompraService.php"
else
    echo -e "${RED}✗${NC} OrdenCompraService.php (no existe)"
fi
echo ""

# Verificar Tests
echo "🧪 Tests Unitarios:"
if [ -f "Backend/tests/Unit/Services/Compras/CompraServiceTest.php" ]; then
    echo -e "${GREEN}✓${NC} CompraServiceTest.php"
else
    echo -e "${RED}✗${NC} CompraServiceTest.php (no existe)"
fi

if [ -f "Backend/tests/Unit/Services/Compras/ComprasAuthorizationServiceTest.php" ]; then
    echo -e "${GREEN}✓${NC} ComprasAuthorizationServiceTest.php"
else
    echo -e "${RED}✗${NC} ComprasAuthorizationServiceTest.php (no existe)"
fi

if [ -f "Backend/tests/Unit/Services/Compras/OrdenCompraServiceTest.php" ]; then
    echo -e "${GREEN}✓${NC} OrdenCompraServiceTest.php"
else
    echo -e "${RED}✗${NC} OrdenCompraServiceTest.php (no existe)"
fi
echo ""

# Verificar Tests de Integración
echo "🔗 Tests de Integración:"
if [ -f "Backend/tests/Feature/Compras/FacturacionTest.php" ]; then
    echo -e "${GREEN}✓${NC} FacturacionTest.php"
else
    echo -e "${RED}✗${NC} FacturacionTest.php (no existe)"
fi
echo ""

# Verificar métodos privados en ComprasController
echo "🔎 Verificando métodos privados en ComprasController:"
PRIVATE_METHODS=$(grep -c "private function" Backend/app/Http/Controllers/Api/Compras/ComprasController.php 2>/dev/null || echo "0")
if [ "$PRIVATE_METHODS" -gt 0 ]; then
    echo -e "${YELLOW}⚠${NC} Encontrados $PRIVATE_METHODS métodos privados (revisar si tienen lógica de negocio)"
    grep "private function" Backend/app/Http/Controllers/Api/Compras/ComprasController.php
else
    echo -e "${GREEN}✓${NC} No hay métodos privados"
fi
echo ""

# Contar líneas del método facturacion
echo "📏 Líneas en método facturacion():"
FACTURACION_LINES=$(grep -A 200 "public function facturacion" Backend/app/Http/Controllers/Api/Compras/ComprasController.php | grep -c "^\s" || echo "0")
if [ "$FACTURACION_LINES" -lt 50 ]; then
    echo -e "${GREEN}✓${NC} Método facturacion() tiene ~$FACTURACION_LINES líneas (objetivo: <50)"
else
    echo -e "${YELLOW}⚠${NC} Método facturacion() tiene ~$FACTURACION_LINES líneas (objetivo: <50)"
fi
echo ""

# Verificar commits
echo "📝 Commits relacionados:"
git log --oneline --grep="compras\|refactor" -10
echo ""

echo "✅ Verificación completada"

