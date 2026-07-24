<?php

namespace Tests\Feature;

use Tests\TestCase;

class FileCategoriesConfigTest extends TestCase
{
    public function test_file_categories_config_has_all_expected_keys(): void
    {
        $cats = config('file_categories');

        $this->assertIsArray($cats);
        foreach (['image', 'video', 'audio', 'pdf', 'document', 'spreadsheet', 'archive'] as $key) {
            $this->assertArrayHasKey($key, $cats, "Missing category: {$key}");
        }
    }

    public function test_each_category_has_mime_or_ext_matcher(): void
    {
        foreach (config('file_categories') as $key => $rule) {
            $hasMatcher = isset($rule['mime']) || isset($rule['ext']);
            $this->assertTrue($hasMatcher, "Category '{$key}' has no mime or ext matcher");
        }
    }
}
