<?php

namespace App\View\Components\Overview;

use App\Enums\ContainerStatus;
use App\Enums\ServerState;
use App\Models\Server;
use Illuminate\View\Component;

class PageHead extends Component
{
    public function __construct(
        public Server $server,
        public ?ContainerStatus $containerStatus = null,
        public bool $transferring = false,
    ) {}

    /** ServerState enum off the model — used by the right-rail state pill. */
    public function state(): ?ServerState
    {
        return $this->server->status;
    }

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

    /**
     * Game tag from the egg's `game:<slug>` namespaced tag.
     * Bedrock displays as "minecraft" in the eyebrow per the decision lock
     * in the 2026-05-13 redesign plan — the tag stays distinct (`game:bedrock`)
     * for routing, but the visible label collapses to minecraft.
     */
    public function game(): ?string
    {
        $tags = (array) ($this->server->egg?->tags ?? []);
        foreach ($tags as $tag) {
            if (is_string($tag) && str_starts_with($tag, 'game:')) {
                $raw = substr($tag, strlen('game:'));

                return $raw === 'bedrock' ? 'minecraft' : $raw;
            }
        }

        return null;
    }

    public function flavour(): ?string
    {
        return $this->server->egg?->name;
    }

    public function version(): ?string
    {
        return $this->server->version;
    }

    public function render()
    {
        return view('components.overview.page-head', [
            // address removed from the payload — the copy button that
            // consumed it is gone. host + port still render the value.
            // PageHead::address() stays public; port() / hostBeforePort()
            // depend on it internally.
            'host' => $this->hostBeforePort(),
            'port' => $this->port(),
            'city' => $this->locationCity(),
            'cc' => $this->locationCountryCode(),
            'game' => $this->game(),
            'flavour' => $this->flavour(),
            'version' => $this->version(),
            'state' => $this->state(),
            'containerStatus' => $this->containerStatus,
            'transferring' => $this->transferring,
            'server' => $this->server,
        ]);
    }
}
