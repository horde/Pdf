<?php

declare(strict_types=1);

use Horde\Pdf\StructureType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(StructureType::class)]
class StructureTypeTest extends TestCase
{
    public function testEnumValuesMatchPdfNames(): void
    {
        $this->assertSame('Document', StructureType::Document->value);
        $this->assertSame('P', StructureType::P->value);
        $this->assertSame('H1', StructureType::H1->value);
        $this->assertSame('Table', StructureType::Table->value);
        $this->assertSame('Figure', StructureType::Figure->value);
        $this->assertSame('BlockQuote', StructureType::BlockQuote->value);
    }

    public function testCaseCount(): void
    {
        $cases = StructureType::cases();
        $this->assertGreaterThanOrEqual(25, count($cases));
    }
}
