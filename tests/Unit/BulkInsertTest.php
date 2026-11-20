<?php

namespace Tests\Unit;

use App\Support\BulkInsert;
use PHPUnit\Framework\TestCase;

class BulkInsertTest extends TestCase
{
    public function test_sqlite_chunks_stay_under_the_variable_limit(): void
    {
        $this->assertSame(60, BulkInsert::size(15, 'sqlite'));
        $this->assertSame(1, BulkInsert::size(5000, 'sqlite'));
        $this->assertSame(900, BulkInsert::size(1, 'sqlite'));
    }

    public function test_other_drivers_use_a_larger_chunk(): void
    {
        $this->assertSame(250, BulkInsert::size(16, 'mysql'));
        $this->assertSame(4000, BulkInsert::size(0, 'mysql'));
        $this->assertSame(900, BulkInsert::size(0, 'sqlite'));
    }
}
