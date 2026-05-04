<?php

namespace App\Services\Nodes;

use App\Models\Node;
use Illuminate\Database\Eloquent\Collection;

class NodeHealthService
{
    public function __construct(private ?int $thresholdSeconds = null) {}

    public function getThreshold(): int
    {
        if ($this->thresholdSeconds !== null) {
            return $this->thresholdSeconds;
        }

        $configured = config('panel.nodes.health_threshold_seconds');

        return is_numeric($configured) && (int) $configured > 0 ? (int) $configured : 120;
    }

    public function isHealthy(Node $node): bool
    {
        return $node->isHealthy($this->getThreshold());
    }

    /**
     * @return Collection<int, Node>
     */
    public function getHealthy(): Collection
    {
        return Node::query()->healthy($this->getThreshold())->get();
    }

    /**
     * @return Collection<int, Node>
     */
    public function getUnhealthy(): Collection
    {
        return Node::query()->unhealthy($this->getThreshold())->get();
    }

    public function hasHealthyNode(): bool
    {
        return Node::query()->healthy($this->getThreshold())->exists();
    }
}
