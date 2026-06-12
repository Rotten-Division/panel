<?php

namespace App\Enums;

enum PortState: string
{
    case Bound = 'bound';
    case Held = 'held';
    case Reserved = 'reserved';
    case Free = 'free';
    case OutOfPool = 'out_of_pool';
}
