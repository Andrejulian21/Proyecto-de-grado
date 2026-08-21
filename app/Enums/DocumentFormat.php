<?php

declare(strict_types=1);

namespace App\Enums;

enum DocumentFormat: string
{
    case Docx = 'docx';
    case Pdf = 'pdf';
    case Unsupported = 'unsupported';
}
