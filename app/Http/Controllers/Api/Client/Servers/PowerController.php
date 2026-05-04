<?php

namespace App\Http\Controllers\Api\Client\Servers;

use App\Contracts\Servers\ServerStartGate;
use App\Facades\Activity;
use App\Http\Controllers\Api\Client\ClientApiController;
use App\Http\Requests\Api\Client\Servers\SendPowerRequest;
use App\Models\Server;
use App\Repositories\Daemon\DaemonServerRepository;
use App\Services\Servers\StartGateDecision;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

#[Group('Server', weight: 2)]
class PowerController extends ClientApiController
{
    /**
     * PowerController constructor.
     */
    public function __construct(
        private DaemonServerRepository $repository,
        private ServerStartGate $startGate,
    ) {
        parent::__construct();
    }

    /**
     * Send power action
     *
     * Send a power action to a server.
     *
     * @throws ConnectionException
     */
    public function index(SendPowerRequest $request, Server $server): Response|JsonResponse
    {
        $signal = $request->input('signal');
        $swapped = null;

        // route start through the panels start gate so the api respects the
        // same one running server policy the ui surfaces enforce. other
        // signals pass through unchanged, the gate is start specific.
        if ($signal === 'start') {
            $decision = $this->startGate->gateStart(
                $server,
                $request->user(),
                fn () => $this->repository->setServer($server)->power('start'),
            );

            if (!$decision->proceeded) {
                $status = $decision->httpStatus();

                return response()->json([
                    'errors' => [[
                        'code' => $decision->outcome,
                        'status' => (string) $status,
                        'detail' => $decision->message,
                    ]],
                ], $status);
            }

            if ($decision->outcome === StartGateDecision::SWAPPED && $decision->stopped !== null) {
                $swapped = $decision->stopped;
            }
        } else {
            $this->repository->setServer($server)->power($signal);
        }

        Activity::event(strtolower("server:power.{$signal}"))->log();

        $response = $this->returnNoContent();

        // surface the swap on a response header so api consumers can detect
        // when a sibling server was stopped to honour the start, the body
        // stays empty so the 204 contract holds for clients that do not
        // care about the gate.
        if ($swapped !== null) {
            $response->headers->set('X-Ospite-Swap-Stopped-Server', (string) $swapped->uuid);
        }

        return $response;
    }
}
