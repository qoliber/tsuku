<?php

declare(strict_types=1);

namespace Qoliber\Tsuku\Tests\Unit\Support;

use PHPUnit\Framework\TestCase;
use Qoliber\Tsuku\Support\DataAccessor;
use Qoliber\Tsuku\Tests\Characterization\CharacterizationUser;

class DataAccessorTest extends TestCase
{
    public function testArrayPaths(): void
    {
        $data = ['a' => ['b' => ['c' => 1]], 'list' => ['x', 'y'], 'a.b' => 'literal', 'zero' => 0, 'null' => null];

        $this->assertSame(1, DataAccessor::get($data, 'a.b.c'));
        $this->assertSame('y', DataAccessor::get($data, 'list.1'));
        $this->assertSame('literal', DataAccessor::get($data, 'a.b'));
        $this->assertSame(0, DataAccessor::get($data, 'zero'));
        $this->assertNull(DataAccessor::get($data, 'null'));
        $this->assertNull(DataAccessor::get($data, 'a.x.c'));
        $this->assertNull(DataAccessor::get($data, 'list.0.x'));
    }

    public function testObjectStrategies(): void
    {
        $user = new CharacterizationUser();

        $this->assertSame('x', DataAccessor::segment($user, 'name'));
        $this->assertTrue(DataAccessor::segment($user, 'active'));
        $this->assertSame(3, DataAccessor::segment($user, 'total'));
        $this->assertSame('nick', DataAccessor::segment($user, 'nickname'));
        $this->assertNull(DataAccessor::segment($user, 'secret'));
        $this->assertNull(DataAccessor::segment($user, 'unknown'));
        $this->assertNull(DataAccessor::segment('scalar', 'x'));
        $this->assertSame(6, DataAccessor::get(['u' => $user], 'u.secretLength'));
    }

    public function testToStringMatchesACast(): void
    {
        $this->assertSame('abc', DataAccessor::toString('abc'));
        $this->assertSame('1', DataAccessor::toString(true));
        $this->assertSame('', DataAccessor::toString(false));
        $this->assertSame('', DataAccessor::toString(null));
        $this->assertSame('1.5', DataAccessor::toString(1.5));
        $this->assertSame('7', DataAccessor::toString(7));
    }

    public function testToStringOfObjectWithoutToStringThrowsLikeACast(): void
    {
        $this->expectException(\Error::class);

        DataAccessor::toString(new \stdClass());
    }
}
