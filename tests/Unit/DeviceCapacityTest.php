<?php

namespace Tests\Unit;

use App\Support\DeviceCapacity;
use Tests\TestCase;

class DeviceCapacityTest extends TestCase
{
    public function test_share_and_labels_stay_separate_for_database_and_archive(): void
    {
        $total = 1000;
        $database = 25;
        $archive = 400;

        $this->assertSame(2.5, DeviceCapacity::share($database, $total));
        $this->assertSame(40.0, DeviceCapacity::share($archive, $total));
        $this->assertSame('2,50%', DeviceCapacity::formatPercent(2.5));
        $this->assertSame('40,0%', DeviceCapacity::formatPercent(40));
        $this->assertSame('0%', DeviceCapacity::formatPercent(0));
        $this->assertSame('< 0,01%', DeviceCapacity::formatPercent(0.004));
        $this->assertSame('1 KB', DeviceCapacity::formatBytes(1024));
        $this->assertSame('1,50 MB', DeviceCapacity::formatBytes(1024 * 1024 + 512 * 1024));
    }

    public function test_directory_bytes_counts_only_files_in_that_folder(): void
    {
        $root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'arsip-capacity-'.uniqid('', true);
        $archive = $root.DIRECTORY_SEPARATOR.'ARSIP TUGAS AKHIR';
        $other = $root.DIRECTORY_SEPARATOR.'lain';

        mkdir($archive, 0777, true);
        mkdir($other, 0777, true);
        file_put_contents($archive.DIRECTORY_SEPARATOR.'tugas.pdf', str_repeat('a', 1200));
        file_put_contents($other.DIRECTORY_SEPARATOR.'abaikan.txt', str_repeat('b', 9000));

        try {
            $this->assertSame(1200, DeviceCapacity::directoryBytes($archive));
            $this->assertSame(0, DeviceCapacity::directoryBytes($root.DIRECTORY_SEPARATOR.'tidak-ada'));
        } finally {
            @unlink($archive.DIRECTORY_SEPARATOR.'tugas.pdf');
            @unlink($other.DIRECTORY_SEPARATOR.'abaikan.txt');
            @rmdir($archive);
            @rmdir($other);
            @rmdir($root);
        }
    }

    public function test_report_compares_free_space_database_and_archive_separately(): void
    {
        $report = DeviceCapacity::report();

        $this->assertArrayHasKey('free_percent', $report);
        $this->assertArrayHasKey('database_percent', $report);
        $this->assertArrayHasKey('archive_percent', $report);
        $this->assertTrue($report['readable']);
        $this->assertGreaterThan(0, $report['total_bytes']);
        $this->assertLessThanOrEqual($report['total_bytes'], $report['free_bytes']);
        $this->assertGreaterThanOrEqual(0, $report['database_bytes']);
        $this->assertGreaterThanOrEqual(0, $report['archive_bytes']);
        $this->assertContains($report['free_level'], ['ample', 'watch', 'low']);
    }
}
