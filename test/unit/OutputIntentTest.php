<?php

declare(strict_types=1);

use Horde\Pdf\OutputIntent;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(OutputIntent::class)]
class OutputIntentTest extends TestCase
{
    public function testDefaults(): void
    {
        $intent = new OutputIntent();
        $this->assertSame('GTS_PDFA1', $intent->subtype);
        $this->assertSame('sRGB', $intent->outputConditionIdentifier);
        $this->assertSame('http://www.color.org', $intent->registryName);
        $this->assertSame('sRGB IEC61966-2.1', $intent->info);
    }

    public function testCustomValues(): void
    {
        $intent = new OutputIntent(
            subtype: 'GTS_PDFX',
            outputConditionIdentifier: 'FOGRA39',
            registryName: 'http://www.color.org',
            info: 'Coated FOGRA39',
        );
        $this->assertSame('GTS_PDFX', $intent->subtype);
        $this->assertSame('FOGRA39', $intent->outputConditionIdentifier);
        $this->assertSame('Coated FOGRA39', $intent->info);
    }
}
