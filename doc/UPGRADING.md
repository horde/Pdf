# Upgrading from Horde_Pdf_Writer to Horde\Pdf

This guide covers migrating your code from the legacy `Horde_Pdf_Writer` (in `lib/`) to the
modern `Horde\Pdf` namespace (in `src/`).

## Two API levels

The modern codebase offers two layers:

- **PdfWriter** is a high-level stateful writer with the same workflow as the legacy
  class (margins, cursor, cell/multiCell/write, auto page break).
- **Object graph** provides low-level primitives (`DocumentCatalog`, `Page`,
  `ContentStreamBuilder`, `PdfSerializer`) for full control over the PDF structure.

Most callers should use `PdfWriter`. Use the object graph when you need features
the writer does not expose such as annotations, custom viewer preferences or direct
coordinate text.

## Quick migration

### Before (legacy)

```php
$pdf = new Horde_Pdf_Writer(['format' => 'Letter', 'unit' => 'pt']);
$pdf->setMargins(50, 50);
$pdf->setAutoPageBreak(true, 50);
$pdf->open();
$pdf->addPage();
$pdf->setFont('Times', 'B', 24);
$pdf->multiCell(0, 24, $title, 'B', 1);
$pdf->newLine(20);
$pdf->setFont('Times', '', 14);
$pdf->write(14, $body);
echo $pdf->getOutput();
```

### After (modern — PdfWriter)

```php
use Horde\Pdf\PdfWriter;

$pdf = PdfWriter::fromLegacy(['format' => 'Letter', 'unit' => 'pt']);
$pdf->setMargins(50, 50);
$pdf->setAutoPageBreak(true, 50);
$pdf->open();
$pdf->addPage();
$pdf->setFont('Times', 'B', 24);
$pdf->multiCell(0, 24, $title, 'B', 'L');
$pdf->newLine(20);
$pdf->setFont('Times', '', 14);
$pdf->write(14, $body);
echo $pdf->getOutput();
```

### After (modern — object graph)

```php
use Horde\Pdf\ContentStreamBuilder;
use Horde\Pdf\CoreFont;
use Horde\Pdf\DocumentCatalog;
use Horde\Pdf\Page;
use Horde\Pdf\PageFormat;
use Horde\Pdf\PdfSerializer;
use Horde\Pdf\Rectangle;

$catalog = new DocumentCatalog();
$page = new Page(Rectangle::fromPageFormat(PageFormat::Letter));
$builder = new ContentStreamBuilder();
$builder
    ->beginText()
    ->setFont(CoreFont::TimesBold->toFont(), 24.0)
    ->moveTextPosition(50.0, 742.0)
    ->showText($title)
    ->endText();
$page->addContentStream($builder->build());
$catalog->addPage($page);
echo (new PdfSerializer())->serialize($catalog);
```

## API changes

### Constructor

| Legacy | Modern |
|---|---|
| `new Horde_Pdf_Writer($params)` | `PdfWriter::fromLegacy($params)` or `new PdfWriter(new WriterOptions(...))` |

The `WriterOptions` class accepts typed arguments:

```php
use Horde\Pdf\{PdfWriter, WriterOptions, Unit, PageFormat, Orientation};

$pdf = new PdfWriter(new WriterOptions(
    unit: Unit::Point,
    format: PageFormat::Letter,
    orientation: Orientation::Portrait,
));
```

### Colors

Legacy uses variadic string parameters:

```php
$pdf->setFillColor('rgb', 0.9, 0.9, 0.9);
$pdf->setTextColor('gray', 0.5);
$pdf->setDrawColor('cmyk', 1.0, 0.0, 0.0, 0.0);
```

Modern uses the `Color` value object:

```php
use Horde\Pdf\Color;

$pdf->setFillColor(Color::rgb(0.9, 0.9, 0.9));
$pdf->setTextColor(Color::gray(0.5));
$pdf->setDrawColor(Color::cmyk(1.0, 0.0, 0.0, 0.0));
$pdf->setFillColor(Color::hex('#FF0000'));
```

### multiCell align parameter

Legacy accepts the align parameter positionally after border, and `1` was silently
treated as left-aligned. Modern requires a string or `TextAlign` enum:

```php
// Legacy — 5th param is align
$pdf->multiCell(0, 24, $text, 'B', 1);

// Modern — use 'L', 'C', 'R', 'J', or TextAlign enum
$pdf->multiCell(0, 24, $text, 'B', 'L');
```

### Header and footer

Legacy uses inheritance:

```php
class MyPdf extends Horde_Pdf_Writer
{
    public function header() { /* ... */ }
    public function footer() { /* ... */ }
}
```

Modern uses composition via `HeaderFooterHandler`:

```php
use Horde\Pdf\HeaderFooterHandler;
use Horde\Pdf\PdfWriter;

class MyHeaderFooter implements HeaderFooterHandler
{
    public function writeHeader(PdfWriter $writer): void { /* ... */ }
    public function writeFooter(PdfWriter $writer): void { /* ... */ }
}

$pdf = new PdfWriter(headerFooter: new MyHeaderFooter());
```

### setInfo

Legacy accepts an array or key/value:

```php
$pdf->setInfo(['Title' => 'My Doc', 'Author' => 'Me']);
```

Modern accepts only key/value calls:

```php
$pdf->setInfo('Title', 'My Doc');
$pdf->setInfo('Author', 'Me');
```

### Border and CellNextPosition

Legacy uses raw integers and strings. Modern accepts the same raw values for
backward compatibility but also offers typed alternatives:

```php
use Horde\Pdf\Border;
use Horde\Pdf\CellNextPosition;

// Legacy style (still works)
$pdf->cell(0, 14, 'Text', 1, 1);
$pdf->cell(0, 14, 'Text', 'LR', 0);

// Typed style
$pdf->cell(0, 14, 'Text', Border::full(), CellNextPosition::NextLine);
$pdf->cell(0, 14, 'Text', Border::sides(left: true, right: true), CellNextPosition::ToRight);
```

## Removed methods

These legacy methods have no direct equivalent on `PdfWriter`:

| Legacy method | Replacement |
|---|---|
| `save($filename)` | `file_put_contents($filename, $pdf->getOutput())` |
| `flush()` | Not needed. `getOutput()` returns the full PDF string. |
| `text($x, $y, $text)` | Use the object graph: `ContentStreamBuilder::moveTextPosition()->showText()` |
| `line($x1, $y1, $x2, $y2)` | Use the object graph: `ContentStreamBuilder::moveTo()->lineTo()->stroke()` |
| `rect($x, $y, $w, $h)` | Use the object graph: `ContentStreamBuilder::rect()->stroke()` |
| `circle($x, $y, $r)` | Use the object graph: Bezier curves via `ContentStreamBuilder::curveTo()` |
| `writeRotated(...)` | Use the object graph: `save()->setTransform()->beginText()...restore()` |
| `addFont(...)` | Not yet supported. Only the 14 core PDF fonts are available. |
| `addLink()` / `setLink()` / `link()` | Use the object graph: `LinkAnnotation` + `UriAction` or `GoToAction` |
| `setPage($n)` | Not supported yet. Pages are written sequentially. |
| `getFillColor()` etc. | Not yet exposed. Track color state in your own code if needed |

## Feature comparison

| Feature | Legacy | PdfWriter | Object graph |
|---|---|---|---|
| Core 14 fonts | Yes | Yes | Yes |
| Custom fonts (TTF) | No | No | No |
| Margins / auto page break | Yes | Yes | N/A (manual) |
| cell / multiCell / write | Yes | Yes | N/A (manual) |
| Header / footer | Subclass | Interface | N/A (manual) |
| {nb} page count alias | Yes | Yes | N/A |
| JPEG images | Yes | Yes | Yes |
| PNG images | Yes | Yes | Yes |
| Drawing (line/rect/circle) | Yes | No | Yes |
| Rotated text | Yes | No | Yes (via transform) |
| Link annotations | Yes | No | Yes |
| Document info | Yes | Yes | Yes |
| Display mode | Yes | Yes | Yes |
| Compression | Yes | Yes | Yes |
| Text color / fill color | Yes | Yes | Yes |
