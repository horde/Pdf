<?php

declare(strict_types=1);

use Horde\Pdf\DocumentInfo;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DocumentInfo::class)]
class DocumentInfoTest extends TestCase
{
    public function testDefaults(): void
    {
        $info = new DocumentInfo();
        $this->assertNull($info->title);
        $this->assertNull($info->author);
        $this->assertNull($info->subject);
        $this->assertNull($info->keywords);
        $this->assertNull($info->creator);
        $this->assertNull($info->creationDate);
    }

    public function testCustomValues(): void
    {
        $info = new DocumentInfo(
            title: 'My PDF',
            author: 'Test Author',
            subject: 'Testing',
            keywords: 'pdf test',
            creator: 'Horde',
            creationDate: 'D:20260426120000',
        );
        $this->assertSame('My PDF', $info->title);
        $this->assertSame('Test Author', $info->author);
        $this->assertSame('Testing', $info->subject);
        $this->assertSame('pdf test', $info->keywords);
        $this->assertSame('Horde', $info->creator);
        $this->assertSame('D:20260426120000', $info->creationDate);
    }
}
