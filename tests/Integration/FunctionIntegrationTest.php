<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class FunctionIntegrationTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testProductCatalogWithFunctions(): void
    {
        $template = '<?xml version="1.0" encoding="UTF-8"?>
<catalog>
@for(products as product)
    <product id="{product.id}">
        <name>@upper(product.name)</name>
        <price>@number_format(product.price, 2)</price>
        <description>@capitalize(product.description)</description>
        <sku>@upper(product.sku)</sku>
        <stock>@?{product.stock > 0 "In stock" : "Out of stock"}</stock>
    </product>
@end
</catalog>';

        $data = [
            'products' => [
                [
                    'id' => 1,
                    'name' => 'laptop computer',
                    'price' => 1299.99,
                    'description' => 'high-performance laptop',
                    'sku' => 'lap-001',
                    'stock' => 15,
                ],
                [
                    'id' => 2,
                    'name' => 'wireless mouse',
                    'price' => 29.99,
                    'description' => 'ergonomic wireless mouse',
                    'sku' => 'mou-002',
                    'stock' => 0,
                ],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = '<?xml version="1.0" encoding="UTF-8"?>
<catalog>
    <product id="1">
        <name>LAPTOP COMPUTER</name>
        <price>1,299.99</price>
        <description>High-performance laptop</description>
        <sku>LAP-001</sku>
        <stock>In stock</stock>
    </product>
    <product id="2">
        <name>WIRELESS MOUSE</name>
        <price>29.99</price>
        <description>Ergonomic wireless mouse</description>
        <sku>MOU-002</sku>
        <stock>Out of stock</stock>
    </product>
</catalog>';

        $this->assertSame($expected, $result);
    }

    public function testCsvReportWithFunctions(): void
    {
        $template = 'Employee Report - Generated: @date("Y-m-d H:i:s")
Name,Department,Salary,Status
@for(employees as employee)
@upper(employee.last),@upper(employee.dept),@number_format(employee.salary, 2, ".", ""),@default(employee.status, "Active")
@end
Total Employees: @length(employees)';

        $data = [
            'employees' => [
                ['first' => 'john', 'last' => 'doe', 'dept' => 'engineering', 'salary' => 85000, 'status' => 'Active'],
                ['first' => 'jane', 'last' => 'smith', 'dept' => 'marketing', 'salary' => 75000, 'status' => ''],
                ['first' => 'bob', 'last' => 'johnson', 'dept' => 'sales', 'salary' => 65000, 'status' => 'Active'],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        // Get the actual date that was generated
        $lines = explode("\n", $result);
        $this->assertStringStartsWith('Employee Report - Generated: ', $lines[0]);
        $this->assertSame('Name,Department,Salary,Status', $lines[1]);
        $this->assertSame('DOE,ENGINEERING,85000.00,Active', $lines[2]);
        $this->assertSame('SMITH,MARKETING,75000.00,Active', $lines[3]);
        $this->assertSame('JOHNSON,SALES,65000.00,Active', $lines[4]);
        $this->assertSame('Total Employees: 3', $lines[5]);
    }

    public function testJsonWithFunctions(): void
    {
        $template = '{
  "users": [@for(users as user)
    {
      "id": {user.id},
      "username": "@lower(user.username)",
      "display_name": "@upper(user.name)",
      "tags": "@join(user.tags, ", ")"
    }@end
  ]
}';

        $data = [
            'users' => [
                ['id' => 1, 'username' => 'JDoe', 'name' => 'john doe', 'tags' => ['admin', 'developer']],
                ['id' => 2, 'username' => 'JSmith', 'name' => 'jane smith', 'tags' => ['user', 'manager']],
            ],
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = '{
  "users": [    {
      "id": 1,
      "username": "jdoe",
      "display_name": "JOHN DOE",
      "tags": "admin, developer"
    }    {
      "id": 2,
      "username": "jsmith",
      "display_name": "JANE SMITH",
      "tags": "user, manager"
    }  ]
}';

        $this->assertSame($expected, $result);
    }

    public function testComplexFunctionChaining(): void
    {
        $template = 'Name: @capitalize(name)
Price: $@number_format(price, 2)
Discount: @?{discount > 0 "Yes" : "None"}
Final: $@number_format(final, 2)
Tags: @join(tags, " | ")
Tags Upper: @upper(tagsJoined)';

        $data = [
            'name' => 'premium widget',
            'price' => 99.99,
            'discount' => 0.15,
            'final' => 84.99,
            'tags' => ['new', 'featured', 'sale'],
            'tagsJoined' => 'new | featured | sale',
        ];

        $result = $this->tsuku->process($template, $data);

        $expected = 'Name: Premium widget
Price: $99.99
Discount: Yes
Final: $84.99
Tags: new | featured | sale
Tags Upper: NEW | FEATURED | SALE';

        $this->assertSame($expected, $result);
    }
}
