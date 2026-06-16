<?php

namespace Tests\Unit;

use App\Models\File;
use Tests\TestCase;

class FileSearchableArrayTest extends TestCase
{
    public function test_flattens_nested_metadata_without_throwing(): void
    {
        $file = new File();
        $file->name = 'report.pdf';
        $file->mime = 'application/pdf';
        $file->metadata = [
            'pages' => 3,
            'exif' => ['Make' => 'Canon', 'Model' => 'EOS'],
            'tags' => ['scan', 'invoice'],
        ];

        $searchable = $file->toSearchableArray();

        $this->assertSame('report.pdf', $searchable['name']);
        $this->assertIsString($searchable['metadata']);
        $this->assertStringContainsString('Canon', $searchable['metadata']);
        $this->assertStringContainsString('EOS', $searchable['metadata']);
        $this->assertStringContainsString('invoice', $searchable['metadata']);
        $this->assertStringContainsString('3', $searchable['metadata']);
    }

    public function test_handles_null_metadata(): void
    {
        $file = new File();
        $file->name = 'x.txt';
        $file->metadata = null;

        $this->assertNull($file->toSearchableArray()['metadata']);
    }
}
