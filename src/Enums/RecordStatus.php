<?php

declare(strict_types=1);

namespace LegacitiForWp\Enums;

enum RecordStatus: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}
