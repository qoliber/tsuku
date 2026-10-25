<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Tsuku;

class MatchDirectiveTest extends TestCase
{
    private Tsuku $tsuku;

    protected function setUp(): void
    {
        $this->tsuku = new Tsuku();
    }

    public function testMatchWithSingleCase(): void
    {
        $template = '@match(status) @case("active") Active Status @end';

        $result = $this->tsuku->process($template, ['status' => 'active']);
        $this->assertSame(' Active Status ', $result);
    }

    public function testMatchWithMultipleCases(): void
    {
        $template = '@match(status)
@case("active") ✓ Active
@case("pending") ⏳ Pending
@case("suspended") ⚠ Suspended
@end';

        $result = $this->tsuku->process($template, ['status' => 'pending']);
        $this->assertSame(' ⏳ Pending
', $result);
    }

    public function testMatchWithDefault(): void
    {
        $template = '@match(status)
@case("active") ✓ Active
@case("pending") ⏳ Pending
@default ❌ Unknown
@end';

        $result = $this->tsuku->process($template, ['status' => 'cancelled']);
        $this->assertSame(' ❌ Unknown
', $result);
    }

    public function testMatchWithNumbers(): void
    {
        $template = '@match(code)
@case(200) OK
@case(404) Not Found
@case(500) Server Error
@end';

        $result = $this->tsuku->process($template, ['code' => 404]);
        $this->assertSame(' Not Found
', $result);
    }

    public function testMatchWithMultipleValuesInCase(): void
    {
        $template = '@match(status)
@case("active", "verified") ✓ Good Status
@case("pending", "review") ⏳ Waiting
@default ❌ Other
@end';

        $result = $this->tsuku->process($template, ['status' => 'verified']);
        $this->assertSame(' ✓ Good Status
', $result);
    }

    public function testMatchWithVariablesInOutput(): void
    {
        $template = '@match(user.role)
@case("admin") Welcome, Admin {user.name}!
@case("user") Hello, {user.name}
@default Guest
@end';

        $result = $this->tsuku->process($template, [
            'user' => ['role' => 'admin', 'name' => 'John'],
        ]);

        $this->assertSame(' Welcome, Admin John!
', $result);
    }

    public function testMatchInLoop(): void
    {
        $template = '@for(users as user){user.name}: @match(user.status) @case("active") ✓ @case("inactive") ✗ @end
@end';

        $result = $this->tsuku->process($template, [
            'users' => [
                ['name' => 'Alice', 'status' => 'active'],
                ['name' => 'Bob', 'status' => 'inactive'],
            ],
        ]);

        // Since 1.4.0 the line break after an inline @end is output, so each user gets a line
        $expected = "Alice:  ✓ \nBob:  ✗ \n";

        $this->assertSame($expected, $result);
    }

    public function testNestedMatchInIf(): void
    {
        $template = '@if(show_status)
Status: @match(status)
@case("active") Active
@case("pending") Pending
@default Unknown
@end
@end';

        $result = $this->tsuku->process($template, [
            'show_status' => true,
            'status' => 'active',
        ]);

        $this->assertSame('Status:  Active
', $result);
    }

    public function testMatchWithLiteralStrings(): void
    {
        $template = '@match("test") @case("test") Matched! @default Not matched @end';

        $result = $this->tsuku->process($template, []);
        $this->assertSame(' Matched! ', $result);
    }

    public function testMatchStopsAtFirstMatch(): void
    {
        $template = '@match(value)
@case(1) First
@case(1) Second
@case(1) Third
@end';

        $result = $this->tsuku->process($template, ['value' => 1]);
        $this->assertSame(' First
', $result);
    }

    public function testMatchWithStringNumbers(): void
    {
        $template = '@match(status) @case("1") One @case("2") Two @end';

        $result = $this->tsuku->process($template, ['status' => '1']);
        $this->assertSame(' One ', $result);
    }

    public function testMatchWithComplexExpressions(): void
    {
        $template = '@match(order.status)
@case("shipped") Order #{order.id} has shipped
@case("delivered") Order #{order.id} delivered
@default Processing order #{order.id}
@end';

        $result = $this->tsuku->process($template, [
            'order' => ['id' => 12345, 'status' => 'shipped'],
        ]);

        $this->assertSame(' Order #12345 has shipped
', $result);
    }

    public function testMatchEmptyDefault(): void
    {
        $template = '@match(status) @case("active") Active @default @end';

        $result = $this->tsuku->process($template, ['status' => 'unknown']);
        $this->assertSame(' ', $result);
    }

    public function testMatchNoCasesWithDefault(): void
    {
        $template = '@match(status) @default Default content @end';

        $result = $this->tsuku->process($template, ['status' => 'anything']);
        $this->assertSame(' Default content ', $result);
    }

    public function testMatchWithLooseComparison(): void
    {
        $template = '@match(value)
@case(1) Integer 1
@case("1") String 1
@end';

        // PHP loose comparison: 1 == "1" is true
        $result = $this->tsuku->process($template, ['value' => 1]);
        $this->assertSame(' Integer 1
', $result);
    }

    public function testMatchWithFunctionsInOutput(): void
    {
        $template = '@match(type)
@case("uppercase") @upper(text)
@case("lowercase") @lower(text)
@default {text}
@end';

        $result = $this->tsuku->process($template, [
            'type' => 'uppercase',
            'text' => 'hello world',
        ]);

        $this->assertSame(' HELLO WORLD
', $result);
    }

    public function testMatchRealWorldStatusBadge(): void
    {
        $template = '@for(orders as order)
Order #{order.id}: @match(order.status)
@case("pending", "processing") <span class="badge badge-warning">{order.status}</span>
@case("shipped", "delivered") <span class="badge badge-success">{order.status}</span>
@case("cancelled", "refunded") <span class="badge badge-danger">{order.status}</span>
@default <span class="badge badge-secondary">{order.status}</span>
@end
@end';

        $result = $this->tsuku->process($template, [
            'orders' => [
                ['id' => 1, 'status' => 'shipped'],
                ['id' => 2, 'status' => 'pending'],
            ],
        ]);

        $expected = 'Order #1:  <span class="badge badge-success">shipped</span>
Order #2:  <span class="badge badge-warning">pending</span>
';

        $this->assertSame($expected, $result);
    }
}
