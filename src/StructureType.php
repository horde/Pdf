<?php

declare(strict_types=1);

namespace Horde\Pdf;

enum StructureType: string
{
    case Document = 'Document';
    case Part = 'Part';
    case Sect = 'Sect';
    case Div = 'Div';
    case P = 'P';
    case H1 = 'H1';
    case H2 = 'H2';
    case H3 = 'H3';
    case H4 = 'H4';
    case H5 = 'H5';
    case H6 = 'H6';
    case Span = 'Span';
    case Link = 'Link';
    case Figure = 'Figure';
    case Table = 'Table';
    case TR = 'TR';
    case TH = 'TH';
    case TD = 'TD';
    case L = 'L';
    case LI = 'LI';
    case Lbl = 'Lbl';
    case LBody = 'LBody';
    case Caption = 'Caption';
    case Annot = 'Annot';
    case BlockQuote = 'BlockQuote';
    case Code = 'Code';
}
