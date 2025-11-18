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

    public function test_match_with_single_case(): void
    {
        $template = '@match(status) @case("active") Active Status @end';

        $result = $this->tsuku->process($template, ['status' => 'active']);
        $this->assertSame(' Active Status ', $result);
    }

    public function test_match_with_multiple_cases(): void
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

    public function test_match_with_default(): void
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

    public function test_match_with_numbers(): void
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

    public function test_match_with_multiple_values_in_case(): void
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

    public function test_match_with_variables_in_output(): void
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

    public function test_match_in_loop(): void
    {
        $template = '@for(users as user){user.name}: @match(user.status) @case("active") ✓ @case("inactive") ✗ @end
@end';

        $result = $this->tsuku->process($template, [
            'users' => [
                ['name' => 'Alice', 'status' => 'active'],
                ['name' => 'Bob', 'status' => 'inactive'],
            ],
        ]);

        $expected = 'Alice:  ✓ Bob:  ✗ ';

        $this->assertSame($expected, $result);
    }

    public function test_nested_match_in_if(): void
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

    public function test_match_with_literal_strings(): void
    {
        $template = '@match("test") @case("test") Matched! @default Not matched @end';

        $result = $this->tsuku->process($template, []);
        $this->assertSame(' Matched! ', $result);
    }

    public function test_match_stops_at_first_match(): void
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

    public function test_match_with_string_numbers(): void
    {
        $template = '@match(status) @case("1") One @case("2") Two @end';

        $result = $this->tsuku->process($template, ['status' => '1']);
        $this->assertSame(' One ', $result);
    }

    public function test_match_with_complex_expressions(): void
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

    public function test_match_empty_default(): void
    {
        $template = '@match(status) @case("active") Active @default @end';

        $result = $this->tsuku->process($template, ['status' => 'unknown']);
        $this->assertSame(' ', $result);
    }

    public function test_match_no_cases_with_default(): void
    {
        $template = '@match(status) @default Default content @end';

        $result = $this->tsuku->process($template, ['status' => 'anything']);
        $this->assertSame(' Default content ', $result);
    }

    public function test_match_with_loose_comparison(): void
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

    public function test_match_with_functions_in_output(): void
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

    public function test_match_real_world_status_badge(): void
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
