<?php

namespace Tests\Feature\Access;

use App\Http\Middleware\AuthorizePanelPath;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class AuthorizePanelPathTest extends AccessTestCase
{
    private function through(string $path, array $headers = [])
    {
        $request = Request::create('/' . ltrim($path, '/'), 'GET', server: $headers);

        return (new AuthorizePanelPath())->handle($request, fn () => response('ok'));
    }

    public function test_role_permission_pattern_grants_matching_paths_only(): void
    {
        $this->makeRoleWith('uji-akses-klien', ['uji.clients.*' => 'clients*']);
        $this->actingAs($this->makeUser('uji-akses-klien'));

        $this->assertSame('ok', $this->through('clients/12/edit')->getContent());

        $denied = $this->through('projects');
        $this->assertTrue($denied->isRedirect());
        $this->assertStringEndsWith('/error/403', $denied->headers->get('Location'));
    }

    public function test_ajax_requests_get_a_plain_403(): void
    {
        $this->actingAs($this->makeUser());

        $this->expectException(HttpException::class);
        $this->through('projects', ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    }

    public function test_public_paths_and_super_admin(): void
    {
        $this->actingAs($this->makeUser());
        $this->assertSame('ok', $this->through('')->getContent());
        $this->assertSame('ok', $this->through('error/403')->getContent());
        $this->assertSame('ok', $this->through('logout')->getContent());

        $this->actingAs($this->makeUser('super-admin'));
        $this->assertSame('ok', $this->through('peran-izin')->getContent());
        $this->assertSame('ok', $this->through('anything/at/all')->getContent());
    }

    public function test_guests_pass_through_to_filament_authentication(): void
    {
        $this->assertSame('ok', $this->through('projects')->getContent());
    }
}
