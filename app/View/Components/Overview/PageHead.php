<?php

namespace App\View\Components\Overview;

use App\Models\Server;
use Illuminate\View\Component;

class PageHead extends Component
{
    public function __construct(public Server $server) {}

    public function address(): string
    {
        return (string) ($this->server->allocation?->address ?? '');
    }

    public function port(): ?string
    {
        $addr = $this->address();

        // ipv6 bracket form: port is whatever follows the bracket and a colon.
        if (str_starts_with($addr, '[') && ($closeBracket = strpos($addr, ']')) !== false) {
            $afterBracket = substr($addr, $closeBracket + 1);

            return str_starts_with($afterBracket, ':') ? substr($afterBracket, 1) : null;
        }

        $colon = strrpos($addr, ':');

        return $colon === false ? null : substr($addr, $colon + 1);
    }

    public function hostBeforePort(): string
    {
        $addr = $this->address();

        if (str_starts_with($addr, '[') && ($closeBracket = strpos($addr, ']')) !== false) {
            $afterBracket = substr($addr, $closeBracket + 1);
            if (str_starts_with($afterBracket, ':')) {
                return substr($addr, 0, $closeBracket + 1);
            }

            return $addr;
        }

        $colon = strrpos($addr, ':');

        return $colon === false ? $addr : substr($addr, 0, $colon);
    }

    public function locationCity(): ?string
    {
        return $this->server->node?->locationCity;
    }

    public function locationCountryCode(): ?string
    {
        return $this->server->node?->locationCountryCode;
    }

    public function render()
    {
        return view('components.overview.page-head');
    }
}
