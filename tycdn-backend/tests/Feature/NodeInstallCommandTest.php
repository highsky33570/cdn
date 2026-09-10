<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CdnflyApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The console has to hand operators an install command that actually works.
 *
 * It used to emit the v5 form unconditionally. Against a v6 master that command
 * runs `agent.sh`, which looks the agent version up via the update API and then
 * fetches `cdnfly-agent-<ver>-<OS>.tar.gz` — a filename v6 never publishes. It
 * 404s on every operating system, which reads like a broken node or a missing
 * vendor package rather than a stale installer.
 *
 * v6 ships `agent_v6.sh`, takes the agent version directly via --ver, and pulls
 * `cdnfly-go-agent-<ver>-linux-amd64.tar.gz`.
 */
class NodeInstallCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_v6_master_gets_the_v6_installer(): void
    {
        $command = $this->commandFor($this->master(['version_name' => 'v6.0.11', 'agent_ver' => '60003']));

        $this->assertStringContainsString('agent_v6.sh', $command);
        $this->assertStringNotContainsString('/agent.sh', $command);

        // --ver takes the *agent* version, not the master's
        $this->assertStringContainsString("--ver 'v6.0.3'", $command);
        $this->assertStringNotContainsString('--master-ver', $command);

        $this->assertStringContainsString("--master-ip '8.210.246.67'", $command);
        $this->assertStringContainsString("--master-host 'panel.tycdn.org'", $command);
        $this->assertStringContainsString("--master-port '14718'", $command);
    }

    /**
     * agent_ver is packed: 60003 is v6.0.3, not v6.0.03 or v60.0.3.
     */
    public function test_the_packed_agent_version_is_decoded(): void
    {
        $this->assertStringContainsString(
            "--ver 'v6.1.12'",
            $this->commandFor($this->master(['version_name' => 'v6.1.0', 'agent_ver' => '60112'])),
        );
    }

    /**
     * Guessing the agent version is exactly what produced the broken command, so
     * an unusable agent_ver must yield no command rather than a wrong one.
     */
    public function test_a_missing_agent_version_yields_no_command(): void
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('getMasterUpgrade')
            ->andReturn(['data' => $this->master(['version_name' => 'v6.0.11', 'agent_ver' => ''])]);

        $this->actingAs($this->admin())
            ->getJson('/api/admin/node-install-command')
            ->assertOk()
            ->assertJsonPath('data.command', null);
    }

    /**
     * A v5 master must keep the installer it actually ships with.
     */
    public function test_a_v5_master_keeps_the_legacy_installer(): void
    {
        $command = $this->commandFor($this->master(['version_name' => 'v5.8.14', 'agent_ver' => '50827']));

        $this->assertStringContainsString('agent.sh', $command);
        $this->assertStringNotContainsString('agent_v6.sh', $command);
        $this->assertStringContainsString('--master-ver v5.8.14', $command);
    }

    public function test_the_cc_image_url_is_appended_when_present(): void
    {
        $command = $this->commandFor($this->master([
            'version_name' => 'v6.0.11',
            'agent_ver' => '60003',
            'cc_img_url' => 'https://cc.tycdn.org',
        ]));

        $this->assertStringContainsString("--cc-img-url 'https://cc.tycdn.org'", $command);
    }

    /**
     * Byte-for-byte equality with what the CDNfly panel itself emits.
     *
     * The console and the panel generate this string independently — CDNfly has
     * no "give me the install command" endpoint, only the ingredients — so the
     * only way to know they agree is to pin the panel's exact output. If CDNfly
     * changes the installer again, this test fails and names the drift instead
     * of an operator discovering it on a half-installed node.
     *
     * Captured from panel.tycdn.org running master v6.0.11.
     */
    public function test_it_matches_the_cdnfly_panels_own_command(): void
    {
        $expected = '(curl -fL --connect-timeout 10 --max-time 60 http://dl2.lotcdn.com/cdnfly/agent_v6.sh -o agent_v6.sh'
            .' || curl -fL --connect-timeout 10 --max-time 60 http://us.lotcdn.com/cdnfly/agent_v6.sh -o agent_v6.sh)'
            ." && chmod 700 agent_v6.sh && ./agent_v6.sh --ver 'v6.0.3' --master-ip '8.210.246.67'"
            ." --es-ip '8.210.246.67' --es-pwd 'secret' --master-host 'panel.tycdn.org' --master-port '14718'";

        $this->assertSame($expected, $this->commandFor($this->master([
            'version_name' => 'v6.0.11',
            'agent_ver' => '60003',
        ])));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function master(array $overrides = []): array
    {
        return array_merge([
            'version_name' => 'v6.0.11',
            'agent_ver' => '60003',
            'ip' => '8.210.246.67',
            'es_ip' => '8.210.246.67',
            'es_pwd' => 'secret',
            'master_host' => 'panel.tycdn.org',
            'master_port' => '14718',
            'cc_img_url' => '',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $master
     */
    private function commandFor(array $master): string
    {
        $cdnfly = $this->mock(CdnflyApiService::class);
        $cdnfly->shouldReceive('getMasterUpgrade')->andReturn(['data' => $master]);

        return (string) $this->actingAs($this->admin())
            ->getJson('/api/admin/node-install-command')
            ->assertOk()
            ->json('data.command');
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }
}
