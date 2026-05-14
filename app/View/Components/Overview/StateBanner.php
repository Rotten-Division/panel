<?php

namespace App\View\Components\Overview;

use Illuminate\View\Component;

class StateBanner extends Component
{
    public function __construct(
        public string $variant = 'default',
        public string $title = '',
        public ?string $subtitle = null,
        public bool $showProgress = false,
        public ?string $icon = null,
    ) {}

    public function render()
    {
        return view('components.overview.state-banner');
    }
}
