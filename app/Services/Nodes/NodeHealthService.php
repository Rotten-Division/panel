<?php

namespace App\Services\Nodes;

use App\Models\Node;
use Illuminate\Database\Eloquent\Collection;

class NodeHealthService
{
    public function __construct(private int $thresholdSeconds = Node::HEALTH_THRESHOLD_SECONDS) {}

    /**
     * Returns the freshness window every consumer agrees on. Centralised so
     * callers do not drift apart on what "healthy" means.
     */
    public function getThreshold(): int
    {
        return $this->thresholdSeconds;
    }

    public function isHealthy(Node $node): bool
    {
        return $node->isHealthy($this->thresholdSeconds);
    }

    /**
     * @return Collection<int, Node>
     */
    public function getHealthy(): Collection
    {
        return Node::query()->healthy($this->thresholdSeconds)->get();
    }

    /**
     * @return Collection<int, Node>
     */
    public function getUnhealthy(): Collection
    {
        return Node::query()->unhealthy($this->thresholdSeconds)->get();
    }

    public function hasHealthyNode(): bool
    {
        return Node::query()->healthy($this->thresholdSeconds)->exists();
    }
}
