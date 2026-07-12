<?php

namespace App\Services;

/**
 * Pure parsers turning raw EXIF/IPTC/XMP blocks into the structured
 * `metadata.photo` payload the photo info panel renders. No IO here — the
 * extractor feeds arrays/strings in, so every rule is unit-testable without
 * crafting binary image fixtures.
 */
class PhotoMetadata
{
    /**
     * Technical camera data from an exif_read_data() array.
     *
     * @param  array<string, mixed>  $exif
     * @return array<string, mixed>
     */
    public function fromExif(array $exif): array
    {
        $photo = array_filter([
            'lens' => $exif['UndefinedTag:0xA434'] ?? $exif['LensModel'] ?? null,
            'exposure' => $exif['ExposureTime'] ?? null,
            'aperture' => $exif['COMPUTED']['ApertureFNumber'] ?? $this->fNumber($exif['FNumber'] ?? null),
            'iso' => $exif['ISOSpeedRatings'] ?? null,
            'focal_length' => $this->focalLength($exif['FocalLength'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');

        if (is_array($photo['iso'] ?? null)) {
            $photo['iso'] = $photo['iso'][0] ?? null;
        }

        if ($gps = $this->gps($exif)) {
            $photo['gps'] = $gps;
        }

        return $photo;
    }

    /**
     * Decimal coordinates from EXIF GPS rationals, or null when absent/broken.
     *
     * @param  array<string, mixed>  $exif
     * @return array{lat: float, lng: float}|null
     */
    public function gps(array $exif): ?array
    {
        if (! isset($exif['GPSLatitude'], $exif['GPSLongitude'])) {
            return null;
        }

        $lat = $this->dms($exif['GPSLatitude']);
        $lng = $this->dms($exif['GPSLongitude']);
        if ($lat === null || $lng === null) {
            return null;
        }

        if (($exif['GPSLatitudeRef'] ?? 'N') === 'S') {
            $lat = -$lat;
        }
        if (($exif['GPSLongitudeRef'] ?? 'E') === 'W') {
            $lng = -$lng;
        }

        return ['lat' => round($lat, 6), 'lng' => round($lng, 6)];
    }

    /**
     * Descriptive fields from iptcparse()'s APP13 array.
     *
     * @param  array<string, array<int, string>>  $iptc
     * @return array<string, mixed>
     */
    public function fromIptc(array $iptc): array
    {
        $one = fn (string $key) => isset($iptc[$key][0]) && trim($iptc[$key][0]) !== '' ? trim($iptc[$key][0]) : null;

        return array_filter([
            'title' => $one('2#005'),
            'caption' => $one('2#120'),
            'keywords' => array_values(array_filter(array_map('trim', $iptc['2#025'] ?? []))) ?: null,
            'credit' => $one('2#080') ?? $one('2#110'),
            'copyright' => $one('2#116'),
            'city' => $one('2#090'),
            'country' => $one('2#101'),
        ], fn ($v) => $v !== null);
    }

    /**
     * Minimal XMP (Dublin Core) parse from the head bytes of a file: title,
     * description, creator, subjects. Regex-scoped to the xmpmeta packet, so a
     * missing/rotten packet just yields [].
     *
     * @return array<string, mixed>
     */
    public function fromXmp(string $head): array
    {
        if (! preg_match('/<x:xmpmeta.*?<\/x:xmpmeta>/s', $head, $m)) {
            return [];
        }
        $xmp = $m[0];

        $items = function (string $tag) use ($xmp): ?array {
            if (! preg_match('/<dc:'.$tag.'>.*?<\/dc:'.$tag.'>/s', $xmp, $block)) {
                return null;
            }
            preg_match_all('/<rdf:li[^>]*>(.*?)<\/rdf:li>/s', $block[0], $lis);
            $values = array_values(array_filter(array_map(
                fn ($v) => trim(html_entity_decode(strip_tags($v))),
                $lis[1] ?? [],
            )));

            return $values ?: null;
        };

        return array_filter([
            'title' => $items('title')[0] ?? null,
            'description' => $items('description')[0] ?? null,
            'creator' => $items('creator')[0] ?? null,
            'subjects' => $items('subject'),
        ], fn ($v) => $v !== null);
    }

    /** "50/1" → "50 mm"; already-numeric values pass through. */
    private function focalLength(mixed $value): ?string
    {
        $n = $this->rational($value);

        return $n === null ? null : rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.').' mm';
    }

    /** "28/10" → "f/2.8". */
    private function fNumber(mixed $value): ?string
    {
        $n = $this->rational($value);

        return $n === null ? null : 'f/'.rtrim(rtrim(number_format($n, 1, '.', ''), '0'), '.');
    }

    /** EXIF rational ("a/b"), numeric string, or number → float. */
    private function rational(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value) && preg_match('/^(-?\d+)\/(\d+)$/', $value, $m) && (int) $m[2] !== 0) {
            return (int) $m[1] / (int) $m[2];
        }

        return null;
    }

    /**
     * Degrees/minutes/seconds rationals → decimal degrees.
     *
     * @param  mixed  $dms  e.g. ["40/1", "2646/100", "0/1"]
     */
    private function dms(mixed $dms): ?float
    {
        if (! is_array($dms) || count($dms) < 1) {
            return null;
        }
        $deg = $this->rational($dms[0] ?? null);
        $min = $this->rational($dms[1] ?? '0') ?? 0.0;
        $sec = $this->rational($dms[2] ?? '0') ?? 0.0;

        return $deg === null ? null : $deg + $min / 60 + $sec / 3600;
    }
}
