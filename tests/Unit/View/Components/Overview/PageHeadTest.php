<?php

use App\Models\Node;
use App\Models\Server;
use App\View\Components\Overview\PageHead;

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
