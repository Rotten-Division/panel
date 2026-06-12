<?php

namespace App\Tests\Integration\Plugins\AllocationRouter;

use App\Models\ApiKey;
use App\Models\Node;
use App\Models\Role;
use App\Models\User;
use App\Services\Acl\Api\AdminAcl;
use App\Tests\Integration\IntegrationTestCase;
use Composer\Autoload\ClassLoader;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class WgPeerRouteTest extends IntegrationTestCase
{
    private User $user;

    // pelican's PluginService::loadPlugins() short-circuits under
    // runningUnitTests(), so the plugin provider, its routes and its
    // migrations never load in the panel test boot. register the provider
    // by hand (its boot() binds the wg-peer route) and create the peers
    // table ourselves so the real application-api auth stack still gets
    // exercised against the real controller and WgPeerRequest.
    protected function setUp(): void
    {
        parent::setUp();

        $pluginSrc = base_path('plugins/ospite-allocation-router/src');
        if (is_dir($pluginSrc)) {
            /** @var ClassLoader $loader */
            $loader = require base_path('vendor/autoload.php');
            $loader->addPsr4('RottenDivision\\OspiteAllocationRouter\\', $pluginSrc);
        }

        config()->set('ospite-allocation-router.backend_cidr', '10.99.0.0/24');

        if (! Schema::hasTable('osw_node_peers')) {
            Schema::create('osw_node_peers', function (Blueprint $table) {
                $table->unsignedInteger('node_id')->primary();
                $table->ipAddress('wg_peer_ip');
                $table->timestamps();
            });
        }

        $this->app->register(\RottenDivision\OspiteAllocationRouter\Providers\OspiteAllocationRouterPluginProvider::class);

        $this->user = User::factory()->create();
        $this->user->syncRoles(Role::getRootAdmin());

        $this->withHeader('Accept', 'application/vnd.panel.v1+json');
    }

    private function node(): Node
    {
        return Node::factory()->create();
    }

    private function applicationKey(int $nodePermission): ApiKey
    {
        return ApiKey::factory()->create([
            'user_id' => $this->user->id,
            'key_type' => ApiKey::TYPE_APPLICATION,
            'permissions' => [Node::RESOURCE_NAME => $nodePermission],
        ]);
    }

    private function url(Node $node): string
    {
        return "/api/application/ospite-router/nodes/{$node->id}/wg-peer";
    }

    public function test_it_401s_without_a_token(): void
    {
        $node = $this->node();

        $this->putJson($this->url($node), ['wg_peer_ip' => '10.99.0.30'])
            ->assertStatus(401);
    }

    public function test_it_403s_with_a_key_lacking_node_write(): void
    {
        $node = $this->node();
        $key = $this->applicationKey(AdminAcl::READ);

        $this->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token)
            ->putJson($this->url($node), ['wg_peer_ip' => '10.99.0.30'])
            ->assertStatus(403);
    }

    public function test_it_204s_and_writes_the_row_with_a_node_write_key_and_an_in_cidr_peer(): void
    {
        $node = $this->node();
        $key = $this->applicationKey(AdminAcl::READ | AdminAcl::WRITE);

        $this->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token)
            ->putJson($this->url($node), ['wg_peer_ip' => '10.99.0.30'])
            ->assertStatus(204);

        $this->assertDatabaseHas('osw_node_peers', [
            'node_id' => $node->id,
            'wg_peer_ip' => '10.99.0.30',
        ]);
    }

    public function test_it_422s_on_an_out_of_cidr_peer(): void
    {
        $node = $this->node();
        $key = $this->applicationKey(AdminAcl::READ | AdminAcl::WRITE);

        $this->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token)
            ->putJson($this->url($node), ['wg_peer_ip' => '192.168.10.30'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('osw_node_peers', ['node_id' => $node->id]);
    }

    public function test_it_422s_on_a_malformed_peer(): void
    {
        $node = $this->node();
        $key = $this->applicationKey(AdminAcl::READ | AdminAcl::WRITE);

        $this->withHeader('Authorization', 'Bearer ' . $key->identifier . $key->token)
            ->putJson($this->url($node), ['wg_peer_ip' => 'not-an-ip'])
            ->assertStatus(422);

        $this->assertDatabaseMissing('osw_node_peers', ['node_id' => $node->id]);
    }
}
