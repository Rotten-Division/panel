<?php

namespace App\View\Components\Overview;

use Illuminate\View\Component;

class StageHero extends Component
{
    public function __construct(public string $variant = 'nest') {}

    public function render()
    {
        return view('components.overview.stage-hero');
    }
}
