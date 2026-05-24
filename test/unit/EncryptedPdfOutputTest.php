<?php

declare(strict_types=1);

use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\EncryptionAlgorithm;
use Horde\Pdf\EncryptionConfig;
use Horde\Pdf\EncryptionHandler;
use Horde\Pdf\EncryptionPermissions;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\PdfWriter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(PdfWriter::class)]
#[CoversClass(PdfSerializer::class)]
#[CoversClass(EncryptionConfig::class)]
#[CoversClass(EncryptionHandler::class)]
#[CoversClass(DocumentCatalog::class)]
class EncryptedPdfOutputTest extends TestCase
{
    public function testAes256EncryptedPdf(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Secret content');
        $pdf->setEncryption(new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES256,
            ownerPassword: 'owner',
            userPassword: 'user',
        ));

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/Filter /Standard', $output);
        $this->assertStringContainsString('/V 5 /R 6 /Length 256', $output);
        $this->assertStringContainsString('/CFM /AESV3', $output);
        $this->assertStringContainsString('/Encrypt ', $output);
        $this->assertStringContainsString('/ID [<', $output);
        $this->assertStringContainsString('/StmF /StdCF /StrF /StdCF', $output);
        $this->assertStringContainsString('/OE <', $output);
        $this->assertStringContainsString('/UE <', $output);
        $this->assertStringContainsString('/Perms <', $output);
        $this->assertStringContainsString('%PDF-2.0', $output);
    }

    public function testAes128EncryptedPdf(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Secret content');
        $pdf->setEncryption(new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES128,
            ownerPassword: 'owner',
            userPassword: 'user',
        ));

        $output = $pdf->getOutput();

        $this->assertStringContainsString('/Filter /Standard', $output);
        $this->assertStringContainsString('/V 4 /R 4 /Length 128', $output);
        $this->assertStringContainsString('/CFM /AESV2', $output);
        $this->assertStringContainsString('/Encrypt ', $output);
        $this->assertStringContainsString('/ID [<', $output);
        $this->assertStringNotContainsString('/OE <', $output);
        $this->assertStringNotContainsString('/UE <', $output);
    }

    public function testEncryptedPdfVersionBump(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Test');
        $pdf->setEncryption(new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES128,
        ));

        $output = $pdf->getOutput();
        // AES-128 requires at least PDF 1.6
        $this->assertStringStartsWith('%PDF-1.', $output);
        $this->assertStringNotContainsString('%PDF-1.4', $output);
    }

    public function testPermissionFlagsInOutput(): void
    {
        $config = new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES256,
            userPassword: 'test',
            permissions: [EncryptionPermissions::Print, EncryptionPermissions::Copy],
        );
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Test');
        $pdf->setEncryption($config);

        $output = $pdf->getOutput();
        $this->assertStringContainsString('/P ' . $config->permissionFlags(), $output);
    }

    public function testNoEncryptionNoEncryptDict(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Plain');

        $output = $pdf->getOutput();
        $this->assertStringNotContainsString('/Filter /Standard', $output);
        $this->assertStringNotContainsString('/Encrypt ', $output);
        $this->assertStringNotContainsString('/ID [<', $output);
    }

    public function testEncryptedStringsAreHexEncoded(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Test');
        $pdf->setInfo('Title', 'My Document');
        $pdf->setEncryption(new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES256,
            userPassword: 'test',
        ));

        $output = $pdf->getOutput();
        // Info dict strings should be hex-encoded when encrypted
        // (not parenthesized literal strings)
        $this->assertStringNotContainsString('/Title (My Document)', $output);
        $this->assertMatchesRegularExpression('/\/Title <[0-9a-f]+>/', $output);
    }

    public function testContentStreamIsEncrypted(): void
    {
        $pdf = new PdfWriter();
        $pdf->setCompression(false);
        $pdf->addPage();
        $pdf->setFont('Helvetica', '', 12);
        $pdf->text(10, 20, 'Visible text in clear');
        $pdf->setEncryption(new EncryptionConfig(
            algorithm: EncryptionAlgorithm::AES256,
            userPassword: 'secret',
        ));

        $output = $pdf->getOutput();
        // The raw text operators should NOT appear in the output
        // (they're encrypted in the content stream)
        $this->assertStringNotContainsString('Visible text in clear', $output);
    }
}
