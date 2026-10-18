<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Pimcore\Bundle\LoginTrackerBundle\Controller\Api\LoginTrackerApiController;
use Pimcore\Bundle\LoginTrackerBundle\PimcoreLoginTrackerBundle;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Repository\SettingsRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\CsvExportService;
use Pimcore\Bundle\LoginTrackerBundle\Service\RetentionService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SecurityInsightsService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\User\UserInterface;

class TestSecurityUser implements UserInterface
{
    public function __construct(
        private readonly string $username,
        private readonly bool $isAdmin,
        private readonly array $permissions = []
    ) {
    }

    public function getUserIdentifier(): string
    {
        return $this->username;
    }

    public function getRoles(): array
    {
        return $this->isAdmin ? ['ROLE_ADMIN'] : ['ROLE_USER'];
    }

    public function eraseCredentials(): void
    {
    }

    public function isAdmin(): bool
    {
        return $this->isAdmin;
    }

    public function isAllowed(string $permission): bool
    {
        return in_array($permission, $this->permissions, true);
    }
}

final class AuthorizationAndApiTest extends TestCase
{
    private function createController(
        ?TestSecurityUser $user = null,
        array $grantedPermissions = []
    ): LoginTrackerApiController {
        $recordRepo = $this->createStub(LoginRecordRepository::class);
        $recordRepo->method('getDashboardMetrics')->willReturn([
            'total_attempts' => 10,
            'successful_logins' => 8,
            'failed_logins' => 2,
            'success_rate' => 80.0,
            'unique_users' => 3,
            'failed_last_24h' => 1,
            'most_recent' => null,
        ]);
        $recordRepo->method('getActivityTimeline')->willReturn([]);
        $recordRepo->method('findPaginated')->willReturn([
            'items' => [],
            'total' => 0,
            'page' => 1,
            'limit' => 25,
            'pages' => 1,
        ]);
        $recordRepo->method('countTotal')->willReturn(10);

        $settingsRepo = $this->createStub(SettingsRepository::class);
        $settingsRepo->method('getAllAsKeyValue')->willReturn([]);
        $settingsService = new SettingsService($settingsRepo);

        $insightsService = $this->createStub(SecurityInsightsService::class);
        $insightsService->method('computeInsights')->willReturn([
            'total_alerts' => 0,
            'has_critical' => false,
            'has_warnings' => false,
            'insights' => [],
            'disclaimer' => 'Test',
        ]);

        $csvService = $this->createStub(CsvExportService::class);
        $retentionService = $this->createStub(RetentionService::class);

        $controller = new LoginTrackerApiController(
            $recordRepo,
            $settingsService,
            $insightsService,
            $csvService,
            $retentionService
        );

        $container = new Container();
        $tokenStorage = new TokenStorage();

        if ($user !== null) {
            $token = new UsernamePasswordToken($user, 'pimcore_studio', $user->getRoles());
            $tokenStorage->setToken($token);
        }

        $authChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authChecker->method('isGranted')
            ->willReturnCallback(function ($attribute) use ($grantedPermissions) {
                return in_array($attribute, $grantedPermissions, true);
            });

        $container->set('security.token_storage', $tokenStorage);
        $container->set('security.authorization_checker', $authChecker);

        $controller->setContainer($container);

        return $controller;
    }

    public function testUnauthenticatedUserDenied(): void
    {
        $controller = $this->createController(null);

        $this->expectException(AccessDeniedException::class);
        $controller->overview(new Request());
    }

    public function testUserWithoutViewPermissionDenied(): void
    {
        $user = new TestSecurityUser('regular_user', false, []);
        $controller = $this->createController($user);

        $this->expectException(AccessDeniedException::class);
        $controller->overview(new Request());
    }

    public function testUserWithViewPermissionCanAccessOverview(): void
    {
        $user = new TestSecurityUser('analyst', false, [PimcoreLoginTrackerBundle::PERMISSION_VIEW]);
        $controller = $this->createController($user);

        $response = $controller->overview(new Request());
        $this->assertSame(200, $response->getStatusCode());

        $content = json_decode((string) $response->getContent(), true);
        $this->assertTrue($content['success']);
        $this->assertSame(10, $content['metrics']['total_attempts']);
    }

    public function testUserWithoutExportPermissionDeniedFromExport(): void
    {
        $user = new TestSecurityUser('analyst', false, [PimcoreLoginTrackerBundle::PERMISSION_VIEW]);
        $controller = $this->createController($user);

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/plugin_login_tracker_export/');
        $controller->export(new Request());
    }

    public function testUserWithoutSettingsPermissionDeniedFromSavingSettings(): void
    {
        $user = new TestSecurityUser('analyst', false, [PimcoreLoginTrackerBundle::PERMISSION_VIEW]);
        $controller = $this->createController($user);

        $request = new Request([], [], [], [], [], [], json_encode(['tracking_enabled' => false]));

        $this->expectException(AccessDeniedException::class);
        $this->expectExceptionMessageMatches('/plugin_login_tracker_settings/');
        $controller->updateSettings($request);
    }

    public function testAdminUserCanAccessAllEndpoints(): void
    {
        $admin = new TestSecurityUser('admin', true, []);
        $controller = $this->createController($admin);

        $overviewRes = $controller->overview(new Request());
        $this->assertSame(200, $overviewRes->getStatusCode());

        $recordsRes = $controller->records(new Request());
        $this->assertSame(200, $recordsRes->getStatusCode());

        $insightsRes = $controller->insights();
        $this->assertSame(200, $insightsRes->getStatusCode());

        $settingsRes = $controller->getSettings();
        $this->assertSame(200, $settingsRes->getStatusCode());
    }
}
