<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class XsdTemplateTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testGenerateXsdSchema(): void
    {
        $template = '<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:element name="{rootElement}">
    <xs:complexType>
      <xs:sequence>
@for(fields as field)
        <xs:element name="{field.name}" type="xs:{field.type}"@if(field.required > 0)
 minOccurs="1"@end
@unless(field.required > 0)
 minOccurs="0"@end
/>
@end
      </xs:sequence>
    </xs:complexType>
  </xs:element>
</xs:schema>';

        $data = [
            'rootElement' => 'Product',
            'fields' => [
                ['name' => 'id', 'type' => 'string', 'required' => 1],
                ['name' => 'name', 'type' => 'string', 'required' => 1],
                ['name' => 'description', 'type' => 'string', 'required' => 0],
                ['name' => 'price', 'type' => 'decimal', 'required' => 1],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = '<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:element name="Product">
    <xs:complexType>
      <xs:sequence>
        <xs:element name="id" type="xs:string" minOccurs="1"/>
        <xs:element name="name" type="xs:string" minOccurs="1"/>
        <xs:element name="description" type="xs:string" minOccurs="0"/>
        <xs:element name="price" type="xs:decimal" minOccurs="1"/>
      </xs:sequence>
    </xs:complexType>
  </xs:element>
</xs:schema>';

        // Full text comparison
        $this->assertEquals($expected, $result);

        // Individual assertions for debugging
        $this->assertStringContainsString('<xs:element name="Product">', $result);
        $this->assertStringContainsString('<xs:element name="id" type="xs:string" minOccurs="1"', $result);
        $this->assertStringContainsString('<xs:element name="description" type="xs:string" minOccurs="0"', $result);
        $this->assertStringContainsString('<xs:element name="price" type="xs:decimal" minOccurs="1"', $result);
    }
}
