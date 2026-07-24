<?php

/**
 * File-type categories shared between FileSearch (query filtering) and the
 * HandleInertiaRequests middleware (shared props for the JS filter UI).
 * Mirrors resources/js/lib/constants.ts FILE_CATEGORIES — keep both in sync.
 *
 * Each entry uses either 'mime' (prefix pattern for SQL LIKE) or 'ext' (array
 * of lowercase extensions). 'label' is shown in the JS filter dropdown.
 */
return [
    'image' => ['mime' => 'image/%', 'label' => 'Images'],
    'video' => ['mime' => 'video/%', 'label' => 'Videos'],
    'audio' => ['mime' => 'audio/%', 'label' => 'Audio'],
    'pdf'   => ['ext'  => ['pdf'], 'label' => 'PDF'],
    'document'    => ['ext' => ['doc', 'docx', 'txt', 'md', 'rtf', 'odt'], 'label' => 'Documents'],
    'spreadsheet' => ['ext' => ['xls', 'xlsx', 'csv', 'ods'], 'label' => 'Spreadsheets'],
    'archive'     => ['ext' => ['zip', 'rar', '7z', 'tar', 'gz'], 'label' => 'Archives'],
];
