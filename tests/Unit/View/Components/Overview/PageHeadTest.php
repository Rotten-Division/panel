<?php

use App\Models\Node;
use App\Models\Server;
use App\Tests\TestCase;
use App\View\Components\Overview\PageHead;

// rendering tests below need the laravel container for the view() helper.
// extends App\Tests\TestCase which boots the app without DB.
uses(TestCase::class);

function pageHeadServer(?string $address, ?string $city = null, ?string $cc = null): Server
{
    $server = new Server();
    // stdClass stub bypasses the Allocation address accessor (ip + port
    // concat) so the parser can be tested against opaque address strings.
    $server->setRelation('allocation', $address === null ? null : (object) ['address' => $address]);

    if ($city || $cc) {
        $node = new Node();
        $node->tags = array_filter([
            $city ? "loc:{$city}" : null,
            $cc ? "cc:{$cc}" : null,
        ]);
        $server->setRelation('node', $node);
    } else {
        $server->setRelation('node', null);
    }

    return $server;
}

test('parses ipv4 host and port', function () {
    $head = new PageHead(pageHeadServer('play.ospite.host:25565'));

    expect($head->hostBeforePort())->toBe('play.ospite.host');
    expect($head->port())->toBe('25565');
});

test('parses ipv6 bracketed host and port', function () {
    $head = new PageHead(pageHeadServer('[::1]:25565'));

    expect($head->hostBeforePort())->toBe('[::1]');
    expect($head->port())->toBe('25565');
});

test('handles host with no port gracefully', function () {
    $head = new PageHead(pageHeadServer('play.ospite.host'));

    expect($head->hostBeforePort())->toBe('play.ospite.host');
    expect($head->port())->toBeNull();
});

test('address returns empty string when allocation is missing', function () {
    $head = new PageHead(pageHeadServer(null));

    expect($head->address())->toBe('');
    expect($head->hostBeforePort())->toBe('');
    expect($head->port())->toBeNull();
});

test('location accessors return null when node has no tags', function () {
    $head = new PageHead(pageHeadServer('host:1'));

    expect($head->locationCity())->toBeNull();
    expect($head->locationCountryCode())->toBeNull();
});

test('location accessors read from node tags when present', function () {
    $head = new PageHead(pageHeadServer('host:1', 'london', 'gb'));

    expect($head->locationCity())->toBe('london');
    expect($head->locationCountryCode())->toBe('gb');
});

test('page head renders location tag inline with address', function () {
    $component = new PageHead(pageHeadServer('play.ospite.host:25565', 'london', 'gb'));

    $rendered = view('components.overview.page-head', [
        'address' => $component->address(),
        'host' => $component->hostBeforePort(),
        'port' => $component->port(),
        'city' => $component->locationCity(),
        'cc' => $component->locationCountryCode(),
    ])->render();

    // address H1 anchor stays so future tests have something stable
    expect($rendered)->toContain('overview-page-head__address');
    expect($rendered)->toContain('London, GB');
    // sanity: loctag sits inside the same flex row as the H1 (no stacked column)
    expect($rendered)->toContain('flex items-center gap-3 min-w-0');
    // stacked-column class from the previous shipped version must be gone
    expect($rendered)->not->toContain('overview-page-head__loc-city');
});

test('page head omits location tag when node has no loc/cc tags', function () {
    $component = new PageHead(pageHeadServer('play.ospite.host:25565'));

    $rendered = view('components.overview.page-head', [
        'address' => $component->address(),
        'host' => $component->hostBeforePort(),
        'port' => $component->port(),
        'city' => $component->locationCity(),
        'cc' => $component->locationCountryCode(),
    ])->render();

    expect($rendered)->not->toContain('London');
});
