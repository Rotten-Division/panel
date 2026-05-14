<?php

namespace App\View\Components\Overview;

use App\Models\Server;
use Illuminate\View\Component;

class TransferDetail extends Component
{
    public function __construct(public Server $server) {}

    /** @return array{step: string, bytes: int, total_bytes: int}|null */
    public function progress(): ?array
    {
        return $this->server->transferProgress;
    }

    public function sourceNode(): ?string
    {
        return $this->server->transfer?->oldNode?->name;
    }

    public function destinationNode(): ?string
    {
        return $this->server->transfer?->newNode?->name;
    }

    public function stepLabel(string $step): string
    {
        return match ($step) {
            'archiving' => 'Archiving server files',
            'uploading' => 'Uploading to destination',
            'extracting' => 'Unpacking on destination',
            'verifying' => 'Verifying integrity',
            'cleanup' => 'Cleaning up temporary files',
            default => ucfirst($step),
        };
    }

    public function bytesLabel(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }
        $units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        return number_format($bytes / (1024 ** $power), $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }

    public function render()
    {
        return view('components.overview.transfer-detail', [
            'progress' => $this->progress(),
            'source' => $this->sourceNode(),
            'destination' => $this->destinationNode(),
        ]);
    }
}
