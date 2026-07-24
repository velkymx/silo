<?php

namespace Tests\Unit;

use App\Services\PhotoMetadata;
use PHPUnit\Framework\TestCase;

class PhotoMetadataTest extends TestCase
{
    private PhotoMetadata $meta;

    protected function setUp(): void
    {
        $this->meta = new PhotoMetadata();
    }

    public function test_exif_technical_fields_are_normalized(): void
    {
        $out = $this->meta->fromExif([
            'ExposureTime' => '1/250',
            'FNumber' => '28/10',
            'ISOSpeedRatings' => [400],
            'FocalLength' => '50/1',
            'LensModel' => 'RF 50mm F1.8',
        ]);

        $this->assertSame('1/250', $out['exposure']);
        $this->assertSame('f/2.8', $out['aperture']);
        $this->assertSame(400, $out['iso']);
        $this->assertSame('50 mm', $out['focal_length']);
        $this->assertSame('RF 50mm F1.8', $out['lens']);
    }

    public function test_computed_aperture_wins_over_raw_fnumber(): void
    {
        $out = $this->meta->fromExif(['COMPUTED' => ['ApertureFNumber' => 'f/1.8'], 'FNumber' => '28/10']);

        $this->assertSame('f/1.8', $out['aperture']);
    }

    public function test_gps_rationals_become_signed_decimals(): void
    {
        $gps = $this->meta->gps([
            'GPSLatitude' => ['40/1', '2646/100', '0/1'],
            'GPSLatitudeRef' => 'S',
            'GPSLongitude' => ['79/1', '58/1', '5640/100'],
            'GPSLongitudeRef' => 'W',
        ]);

        $this->assertEqualsWithDelta(-40.441, $gps['lat'], 0.001);
        $this->assertEqualsWithDelta(-79.982333, $gps['lng'], 0.001);
    }

    public function test_gps_is_null_when_absent_or_broken(): void
    {
        $this->assertNull($this->meta->gps([]));
        $this->assertNull($this->meta->gps(['GPSLatitude' => 'garbage', 'GPSLongitude' => ['1/0']]));
    }

    public function test_iptc_descriptive_fields(): void
    {
        $out = $this->meta->fromIptc([
            '2#005' => ['Sunset'],
            '2#120' => ['Golden hour over the ridge'],
            '2#025' => ['sunset', 'mountains', ' '],
            '2#080' => ['A. Photographer'],
            '2#116' => ['© 2026'],
            '2#090' => ['Pittsburgh'],
            '2#101' => ['USA'],
        ]);

        $this->assertSame('Sunset', $out['title']);
        $this->assertSame('Golden hour over the ridge', $out['caption']);
        $this->assertSame(['sunset', 'mountains'], $out['keywords']);
        $this->assertSame('A. Photographer', $out['credit']);
        $this->assertSame('© 2026', $out['copyright']);
        $this->assertSame('Pittsburgh', $out['city']);
        $this->assertSame('USA', $out['country']);
    }

    public function test_xmp_dublin_core_parse(): void
    {
        $head = 'junkbytes<x:xmpmeta xmlns:x="adobe:ns:meta/"><rdf:RDF>'
            .'<dc:title><rdf:Alt><rdf:li xml:lang="x-default">Ridge Line</rdf:li></rdf:Alt></dc:title>'
            .'<dc:description><rdf:Alt><rdf:li>An evening walk</rdf:li></rdf:Alt></dc:description>'
            .'<dc:creator><rdf:Seq><rdf:li>Alan</rdf:li></rdf:Seq></dc:creator>'
            .'<dc:subject><rdf:Bag><rdf:li>hiking</rdf:li><rdf:li>autumn</rdf:li></rdf:Bag></dc:subject>'
            .'</rdf:RDF></x:xmpmeta>morejunk';

        $out = $this->meta->fromXmp($head);

        $this->assertSame('Ridge Line', $out['title']);
        $this->assertSame('An evening walk', $out['description']);
        $this->assertSame('Alan', $out['creator']);
        $this->assertSame(['hiking', 'autumn'], $out['subjects']);
    }

    public function test_xmp_absent_yields_empty(): void
    {
        $this->assertSame([], $this->meta->fromXmp('no packet here'));
    }
}
