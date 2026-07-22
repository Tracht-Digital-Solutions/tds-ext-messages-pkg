<?php
declare(strict_types=1);

namespace Tds\Ext\Messages\Tests;

use DI\Container;
use PHPUnit\Framework\TestCase;
use Slim\Factory\AppFactory;
use Slim\Psr7\Factory\ServerRequestFactory;
use Tds\Ext\Messages\MessagesModule;
use Tds\Frontend\Contract\UserContext;

/** A configurable UserContext double (no live JWT needed). */
final class FakeUser implements UserContext
{
    /** @param string[] $perms */
    public function __construct(
        private bool $auth = true,
        private bool $admin = false,
        private array $perms = [],
        private ?int $company = null,
        private ?int $uid = 1,
    ) {
    }

    public function isAuthenticated(): bool
    {
        return $this->auth;
    }

    public function userId(): ?int
    {
        return $this->uid;
    }

    public function email(): ?string
    {
        return null;
    }

    public function isAdmin(): bool
    {
        return $this->admin;
    }

    /** @return string[] */
    public function permissions(): array
    {
        return $this->perms;
    }

    public function has(string $permission): bool
    {
        return $this->admin || in_array($permission, $this->perms, true);
    }

    public function activeCompanyId(): ?int
    {
        return $this->company;
    }
}

/**
 * Route + RBAC + validation coverage that needs no DB: the auth checks and body
 * validation short-circuit before any repository (PDO) access. Full data paths
 * are covered by DB-gated integration tests in the composed API.
 */
final class MessagesModuleTest extends TestCase
{
    private function appWith(UserContext $user): \Slim\App
    {
        $container = new Container();
        $container->set(UserContext::class, $user);
        AppFactory::setContainer($container);
        $app = AppFactory::create();
        $app->addBodyParsingMiddleware();
        $app->addRoutingMiddleware();
        (new MessagesModule())->register($app);
        return $app;
    }

    private function get(\Slim\App $app, string $path): \Psr\Http\Message\ResponseInterface
    {
        return $app->handle((new ServerRequestFactory())->createServerRequest('GET', $path));
    }

    /** @param array<string,mixed> $body */
    private function send(\Slim\App $app, string $method, string $path, array $body): \Psr\Http\Message\ResponseInterface
    {
        $req = (new ServerRequestFactory())->createServerRequest($method, $path)
            ->withHeader('Content-Type', 'application/json')
            ->withParsedBody($body);
        return $app->handle($req);
    }

    public function testMetadata(): void
    {
        $module = new MessagesModule();
        self::assertSame('messages', $module->id());
        $ids = array_map(static fn ($p): string => $p->id, $module->permissions());
        self::assertSame(['messages:read', 'messages:write'], $ids);
        self::assertDirectoryExists($module->migrations()[0]);
    }

    public function testUnauthenticatedListUnauthorized(): void
    {
        self::assertSame(401, $this->get($this->appWith(new FakeUser(auth: false)), '/messages')->getStatusCode());
    }

    public function testReadWithoutPermissionForbidden(): void
    {
        self::assertSame(403, $this->get($this->appWith(new FakeUser(perms: [])), '/messages')->getStatusCode());
    }

    public function testSummaryRequiresReadPermission(): void
    {
        self::assertSame(403, $this->get($this->appWith(new FakeUser(perms: [])), '/messages/summary')->getStatusCode());
    }

    public function testCreateRequiresWritePermission(): void
    {
        $res = $this->send($this->appWith(new FakeUser(perms: ['messages:read'])), 'POST', '/messages', ['body' => 'hi']);
        self::assertSame(403, $res->getStatusCode());
    }

    public function testCreateRejectsEmptyBody(): void
    {
        $res = $this->send($this->appWith(new FakeUser(admin: true)), 'POST', '/messages', ['body' => '  ']);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testCreateRejectsOverlongBody(): void
    {
        $res = $this->send($this->appWith(new FakeUser(admin: true)), 'POST', '/messages', ['body' => str_repeat('x', 10001)]);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testCustomerWithoutCompanyCannotPost(): void
    {
        $res = $this->send($this->appWith(new FakeUser(perms: ['messages:write'], company: null)), 'POST', '/messages', ['body' => 'hi']);
        self::assertSame(422, $res->getStatusCode());
    }

    public function testPatchRequiresBody(): void
    {
        $res = $this->send($this->appWith(new FakeUser(admin: true)), 'PATCH', '/messages/5', []);
        self::assertSame(400, $res->getStatusCode());
    }
}
