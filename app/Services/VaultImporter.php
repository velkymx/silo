<?php

namespace App\Services;

/**
 * Parses a Chrome password CSV export (header: name,url,username,password,note)
 * into normalized rows ready to become VaultItems. Operates purely in memory —
 * the raw file with plaintext passwords is never persisted.
 */
class VaultImporter
{
    /**
     * @return array<int, array{name: string, url: ?string, username: ?string, secret: string, notes: ?string}>
     */
    public function parse(string $csv): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $csv) ?: [];
        $header = null;
        $rows = [];

        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            $cols = str_getcsv($line, ',', '"', '');

            if ($header === null) {
                $header = array_map(fn ($h) => strtolower(trim((string) $h)), $cols);

                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($cols[$i]) ? trim((string) $cols[$i]) : null;
            }

            $secret = $row['password'] ?? '';
            if ($secret === '' || $secret === null) {
                continue; // a row without a password isn't a usable secret
            }

            $url = $row['url'] ?? null;
            $rows[] = [
                'name' => ($row['name'] ?? '') !== '' ? $row['name'] : ($this->hostOf($url) ?? 'Imported'),
                'url' => $url ?: null,
                'username' => ($row['username'] ?? '') !== '' ? $row['username'] : null,
                'secret' => $secret,
                'notes' => ($row['note'] ?? '') !== '' ? ($row['note'] ?? null) : null,
            ];
        }

        return $rows;
    }

    private function hostOf(?string $url): ?string
    {
        return $url ? (parse_url($url, PHP_URL_HOST) ?: null) : null;
    }
}
