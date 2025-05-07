<?php

namespace App\Constants\FacturacionElectronica;

class FEConstants
{

    // 01 Factura
    // 03 Comprobante de crédito fiscal
    // 04 Nota de remisión 
    // 05 Nota de crédito 
    // 06 Nota de débito
    // 07 Comprobante de retención 
    // 08 Comprobante de liquidación 
    // 09 Documento contable de liquidación 
    // 11 Facturas de exportación 
    // 14 Factura de sujeto excluido 
    // 15 Comprobante de donación

    // Tipos de Documentos
    const TIPO_DTE_FACTURA_CONSUMIDOR_FINAL = "01";
    const TIPO_DTE_COMPROBANTE_DE_CREDITO_FISCAL = "03";
    const TIPO_DTE_NOTA_DE_REMISION = "04";
    const TIPO_DTE_NOTA_DE_CREDITO = "05";
    const TIPO_DTE_NOTA_DE_DEBITO = "06";
    const TIPO_DTE_COMPROBANTE_DE_RETENCION = "07";
    const TIPO_DTE_COMPROBANTE_DE_LIQUIDACION = "08";
    const TIPO_DTE_DOCUMENTO_CONTABLE_DE_LIQUIDACION = "09";
    const TIPO_DTE_FACTURAS_DE_EXPORTACION = "11";
    const TIPO_DTE_FACTURA_DE_SUJETO_EXCLUIDO = "14";
    const TIPO_DTE_COMPROBANTE_DE_DONACION = "15";

}