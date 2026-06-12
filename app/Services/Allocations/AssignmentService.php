<?php

namespace App\Services\Allocations;

use App\Contracts\Servers\NodeRoutableGate;
use App\Contracts\Servers\PortDisposition;
use App\Enums\PortState;
use App\Exceptions\DisplayException;
use App\Exceptions\Servers\PortClaimConflictException;
use App\Exceptions\Service\Allocation\CidrOutOfRangeException;
use App\Exceptions\Service\Allocation\InvalidPortMappingException;
use App\Exceptions\Service\Allocation\PortOutOfRangeException;
use App\Exceptions\Service\Allocation\TooManyPortsInRangeException;
use App\Models\Allocation;
use App\Models\Node;
use App\Models\Server;
use App\Services\Servers\PortClaim;
use Exception;
use Illuminate\Database\ConnectionInterface;
use IPTools\Network;

class AssignmentService
{
    public const CIDR_MAX_BITS = 25;

    public const CIDR_MIN_BITS = 32;

    public const PORT_FLOOR = 1024;

    public const PORT_CEIL = 65535;

    public const PORT_RANGE_LIMIT = 1000;

    public const PORT_RANGE_REGEX = '/^(\d{4,5})-(\d{4,5})$/';

    public function __construct(
        protected ConnectionInterface $connection,
        protected PortClaim $portClaim,
        protected PortDisposition $portDisposition,
        protected NodeRoutableGate $nodeRoutableGate,
    ) {}

    /**
     * Insert allocations into the database and link them to a specific node.
     *
     * @param  array{allocation_ip: string, allocation_ports: array<int|string>}  $data
     * @return array<int>
     *
     * @throws DisplayException
     * @throws CidrOutOfRangeException
     * @throws InvalidPortMappingException
     * @throws PortOutOfRangeException
     * @throws TooManyPortsInRangeException
     */
    public function handle(Node $node, array $data, ?Server $server = null): array
    {
        $explode = explode('/', $data['allocation_ip']);
        if (count($explode) !== 1) {
            if (!ctype_digit($explode[1]) || ($explode[1] > self::CIDR_MIN_BITS || $explode[1] < self::CIDR_MAX_BITS)) {
                throw new CidrOutOfRangeException();
            }
        }

        try {
            $parsed = Network::parse($data['allocation_ip']);
        } catch (Exception $exception) {
            throw new DisplayException("Could not parse provided allocation IP address ({$data['allocation_ip']}): {$exception->getMessage()}", $exception);
        }

        // wrap in connection->transaction so any throw rolls back. the
        // earlier beginTransaction plus commit pair leaked an open
        // transaction onto the connection on every validation throw,
        // which surfaced as confusing state on long-lived workers.
        return $this->connection->transaction(function () use ($parsed, $data, $node, $server) {
            $rows = [];
            foreach ($parsed as $ip) {
                foreach ($data['allocation_ports'] as $port) {
                    if (!is_digit($port) && !preg_match(self::PORT_RANGE_REGEX, $port)) {
                        throw new InvalidPortMappingException($port);
                    }

                    if (preg_match(self::PORT_RANGE_REGEX, $port, $matches)) {
                        $block = range($matches[1], $matches[2]);

                        if (count($block) > self::PORT_RANGE_LIMIT) {
                            throw new TooManyPortsInRangeException();
                        }

                        if ((int) $matches[1] < self::PORT_FLOOR || (int) $matches[2] > self::PORT_CEIL) {
                            throw new PortOutOfRangeException();
                        }

                        foreach ($block as $unit) {
                            $rows[] = [
                                'node_id' => $node->id,
                                'ip' => $ip->__toString(),
                                'port' => (int) $unit,
                                'ip_alias' => array_get($data, 'allocation_alias'),
                                'server_id' => $server->id ?? null,
                                'is_locked' => array_get($data, 'is_locked', false),
                            ];
                        }
                    } else {
                        if ((int) $port < self::PORT_FLOOR || (int) $port > self::PORT_CEIL) {
                            throw new PortOutOfRangeException();
                        }

                        $rows[] = [
                            'node_id' => $node->id,
                            'ip' => $ip->__toString(),
                            'port' => (int) $port,
                            'ip_alias' => array_get($data, 'allocation_alias'),
                            'server_id' => $server->id ?? null,
                            'is_locked' => array_get($data, 'is_locked', false),
                        ];
                    }
                }
            }

            $insert = function () use ($rows, $server): array {
                $ids = [];
                foreach ($rows as $row) {
                    $ids[] = Allocation::query()->create($row)->id;
                }

                if ($server && !$server->allocation_id && $ids !== []) {
                    $server->update(['allocation_id' => $ids[0]]);
                }

                return $ids;
            };

            // creating bare node inventory (no server) needs no claim.
            if ($server === null) {
                return $insert();
            }

            // binding new allocations directly to a server is a fleet-wide claim, same
            // as the wizard and network-tab paths: refuse a port owned on any node and a
            // node with no routing peer, then insert the bound rows under the port lock.
            $ports = array_values(array_unique(array_map(static fn (array $row): int => (int) $row['port'], $rows)));

            return $this->portClaim->withClaims($ports, function () use ($ports, $node, $insert): array {
                if (!$this->nodeRoutableGate->routable($node->id)) {
                    throw new DisplayException('The target node has no routing peer and cannot host this server.');
                }
                foreach ($ports as $port) {
                    if ($this->portDisposition->for($port) !== PortState::Free) {
                        throw new PortClaimConflictException();
                    }
                }

                return $insert();
            });
        });
    }
}
