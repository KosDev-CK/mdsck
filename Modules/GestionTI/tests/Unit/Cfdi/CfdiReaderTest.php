<?php

namespace Modules\GestionTI\Tests\Unit\Cfdi;

use Modules\GestionTI\Support\Cfdi\CfdiReader;
use Tests\TestCase;

class CfdiReaderTest extends TestCase
{
    private const CFDI_VALIDO = <<<'XML'
        <?xml version="1.0" encoding="UTF-8"?>
        <cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" xmlns:tfd="http://www.sat.gob.mx/TimbreFiscalDigital"
            Version="4.0" Fecha="2026-08-15T10:30:00" Total="11600.00" Moneda="MXN">
            <cfdi:Emisor Rfc="ABC010101AB1" Nombre="Proveedor de Prueba SA de CV" />
            <cfdi:Receptor Rfc="XYZ020202XY2" Nombre="Grupo Kosmos" />
            <cfdi:Complemento>
                <tfd:TimbreFiscalDigital UUID="11111111-2222-3333-4444-555555555555" />
            </cfdi:Complemento>
        </cfdi:Comprobante>
        XML;

    public function test_parses_key_fields_from_a_valid_cfdi(): void
    {
        $resultado = CfdiReader::parse(self::CFDI_VALIDO);

        $this->assertSame('11111111-2222-3333-4444-555555555555', $resultado['uuid']);
        $this->assertSame('2026-08-15T10:30:00', $resultado['fecha']);
        $this->assertSame('11600.00', $resultado['total']);
        $this->assertSame('MXN', $resultado['moneda']);
        $this->assertSame('ABC010101AB1', $resultado['emisor_rfc']);
        $this->assertSame('Proveedor de Prueba SA de CV', $resultado['emisor_nombre']);
        $this->assertSame('XYZ020202XY2', $resultado['receptor_rfc']);
        $this->assertSame('Grupo Kosmos', $resultado['receptor_nombre']);
    }

    public function test_returns_null_for_malformed_xml(): void
    {
        $this->assertNull(CfdiReader::parse('<esto no es xml válido'));
    }

    public function test_returns_null_for_valid_xml_that_is_not_a_cfdi(): void
    {
        $this->assertNull(CfdiReader::parse('<algo><otra-cosa>sin comprobante</otra-cosa></algo>'));
    }

    public function test_returns_partial_data_when_timbre_is_missing(): void
    {
        $sinTimbre = <<<'XML'
            <?xml version="1.0" encoding="UTF-8"?>
            <cfdi:Comprobante xmlns:cfdi="http://www.sat.gob.mx/cfd/4" Fecha="2026-08-15T10:30:00" Total="500.00" Moneda="MXN">
                <cfdi:Emisor Rfc="ABC010101AB1" Nombre="Proveedor" />
                <cfdi:Receptor Rfc="XYZ020202XY2" Nombre="Kosmos" />
            </cfdi:Comprobante>
            XML;

        $resultado = CfdiReader::parse($sinTimbre);

        $this->assertNull($resultado['uuid']);
        $this->assertSame('500.00', $resultado['total']);
    }
}
