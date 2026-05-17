<?php

use App\Models\Egg;
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

/**
 * Stub a Server with an Egg whose tags drive the page-head eyebrow's
 * flavour/version line. Server::version is a virtual Attribute that
 * walks $this->variables looking for the env var named by egg->versionVar
 * (which itself parses the `version_var:` tag). To exercise that path
 * without the DB, stub the matching variable as a related model.
 */
function pageHeadServerWithEgg(array $eggTags, ?string $eggName = null, ?string $serverVersion = null): Server
{
    $egg = new Egg();
    $egg->tags = $eggTags;
    $egg->name = $eggName;

    $server = pageHeadServer('play.ospite.host:25565');
    $server->setRelation('egg', $egg);

    // when a version is supplied, fake a matching ServerVariable bound to
    // the egg's version_var tag value. Server::version reads via
    // ->variables->firstWhere('env_variable', $varName) — collection of
    // stdClass objects with the right env_variable + server_value satisfies
    // the firstWhere lookup without hitting the EggVariable / ServerVariable
    // models or the database.
    if ($serverVersion !== null && $egg->versionVar !== null) {
        $server->setRelation('variables', collect([
            (object) [
                'env_variable' => $egg->versionVar,
                'server_value' => $serverVersion,
            ],
        ]));
    } else {
        $server->setRelation('variables', collect());
    }

    return $server;
}

/**
 * Render the page-head with the public accessor outputs so we exercise
 * the same data path the live page uses.
 */
function renderPageHead(PageHead $component): string
{
    return view('components.overview.page-head', [
        'server' => $component->server,
        'host' => $component->hostBeforePort(),
        'port' => $component->port(),
        'city' => $component->locationCity(),
        'cc' => $component->locationCountryCode(),
        'flavour' => $component->flavour(),
        'version' => $component->version(),
        'state' => $component->server->status,
        'containerStatus' => $component->containerStatus,
        'transferring' => $component->transferring,
    ])->render();
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
    $rendered = renderPageHead($component);

    // address H1 anchor stays so future tests have something stable
    expect($rendered)->toContain('overview-page-head__address');
    expect($rendered)->toContain('London, GB');
    // sanity: loctag sits inside the same flex row as the H1 (no stacked column)
    expect($rendered)->toContain('flex items-center gap-4 min-w-0');
    // stacked-column class from the previous shipped version must be gone
    expect($rendered)->not->toContain('overview-page-head__loc-city');
});

test('page head omits location tag when node has no loc/cc tags', function () {
    $component = new PageHead(pageHeadServer('play.ospite.host:25565'));
    $rendered = renderPageHead($component);

    expect($rendered)->not->toContain('London');
});

// ── eyebrow (flavour · version) ──────────────────────────────────

test('flavour accessor returns the egg name', function () {
    $server = pageHeadServerWithEgg([], 'Forge');
    $component = new PageHead($server);

    expect($component->flavour())->toBe('Forge');
});

test('version accessor returns the server version attribute', function () {
    // egg must have a version_var: tag so Egg::versionVar resolves; the
    // stub helper then injects a matching ServerVariable.
    $server = pageHeadServerWithEgg(['version_var:MC_VERSION'], 'Forge', '1.21.4');
    $component = new PageHead($server);

    expect($component->version())->toBe('1.21.4');
});

test('version accessor returns null when egg has no version_var tag', function () {
    $server = pageHeadServerWithEgg([], 'Forge');
    $component = new PageHead($server);

    expect($component->version())->toBeNull();
});

test('page head renders the eyebrow with flavour · version', function () {
    $server = pageHeadServerWithEgg(['version_var:MC_VERSION'], 'Forge', '1.21.4');
    $rendered = renderPageHead(new PageHead($server));

    expect($rendered)->toContain('Forge');
    expect($rendered)->toContain('1.21.4');
    // 0.16em letter-spacing matches the canvas eyebrow spec
    expect($rendered)->toContain('tracking-[0.16em]');
});

test('page head omits the eyebrow div when egg has no flavour/version', function () {
    $server = pageHeadServer('play.ospite.host:25565');
    $rendered = renderPageHead(new PageHead($server));

    // no eyebrow markup at all when both eyebrow parts are missing
    expect($rendered)->not->toContain('tracking-[0.16em]');
});
