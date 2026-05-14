<?php

namespace App\View\Components\Overview;

use Illuminate\View\Component;

class FactCards extends Component
{
    /** @param array<int, array{label: string, value: string, icon?: string, variant?: string}> $cards */
    public function __construct(public array $cards = []) {}

    public function render()
    {
        return view('components.overview.fact-cards');
    }
}
