<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Tests\EventSubscriber;

use PHPUnit\Framework\TestCase;
use Pimcore\Bundle\LoginTrackerBundle\Entity\LoginRecord;
use Pimcore\Bundle\LoginTrackerBundle\EventSubscriber\LoginEventSubscriber;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Repository\SettingsRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;
use Pimcore\Model\User as PimcoreUser;
use Pimcore\Security\User\User as SecurityUser;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\JsonLoginAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginEventSubscriberTest extends TestCase
{
    private function createSettingsService(array $config = []): SettingsService
    {
        $repo = $this->createStub(SettingsRepository::class);
        $repo->method('getAllAsKeyValue')->willReturn($config);

        return new SettingsService($repo);
    }

    public function testSuccessfulLoginCaptured(): void
    {
        $savedRecord = null;
        $repo = $this->createMock(LoginRecordRepository::class);
        $repo->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (LoginRecord $record) use (&$savedRecord) {
                $savedRecord = $record;
            });

        $settings = $this->createSettingsService();
        $subscriber = new LoginEventSubscriber($repo, $settings, new NullLogger());

        $request = Request::create('/pimcore-studio/api/login', 'POST');
        $request->server->set('REMOTE_ADDR', '198.51.100.25');
        $request->headers->set('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/124.0.0.0 Safari/537.36');

        $user = new InMemoryUser('admin', 'password');
        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn() => $user));
        $token = new UsernamePasswordToken($user, 'pimcore_studio', ['ROLE_ADMIN']);
        $authenticator = $this->createStub(JsonLoginAuthenticator::class);

        $event = new LoginSuccessEvent(
            $authenticator,
            $passport,
            $token,
            $request,
            new Response(),
            'pimcore_studio'
        );

        $subscriber->onLoginSuccess($event);

        $this->assertNotNull($savedRecord);
        $this->assertSame('admin', $savedRecord->getUsername());
        $this->assertSame(LoginRecord::STATUS_SUCCESS, $savedRecord->getStatus());
        $this->assertSame(LoginRecord::AUTH_METHOD_PASSWORD, $savedRecord->getAuthMethod());
        $this->assertSame('198.51.100.25', $savedRecord->getIpAddress());
        $this->assertSame('Chrome 124', $savedRecord->getBrowser());
        $this->assertSame('macOS', $savedRecord->getOs());
        $this->assertSame('pimcore_studio', $savedRecord->getFirewall());
    }

    public function testFailedLoginCaptured(): void
    {
        $savedRecord = null;
        $repo = $this->createMock(LoginRecordRepository::class);
        $repo->expects($this->once())
            ->method('save')
            ->willReturnCallback(function (LoginRecord $record) use (&$savedRecord) {
                $savedRecord = $record;
            });

        $settings = $this->createSettingsService();
        $subscriber = new LoginEventSubscriber($repo, $settings, new NullLogger());

        $request = Request::create(
            '/pimcore-studio/api/login',
            'POST',
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'REMOTE_ADDR' => '203.0.113.195'],
            json_encode(['username' => 'victim_user', 'password' => 'wrongpass'])
        );

        $passport = new SelfValidatingPassport(new UserBadge('victim_user'));
        $authenticator = $this->createStub(JsonLoginAuthenticator::class);
        $exception = new BadCredentialsException('Invalid credentials.');

        $event = new LoginFailureEvent(
            $exception,
            $authenticator,
            $request,
            new Response('', 401),
            'pimcore_studio',
            $passport
        );

        $subscriber->onLoginFailure($event);

        $this->assertNotNull($savedRecord);
        $this->assertSame('victim_user', $savedRecord->getUsername());
        $this->assertSame(LoginRecord::STATUS_FAILED, $savedRecord->getStatus());
        $this->assertSame('Invalid username or password', $savedRecord->getFailureReason());
        $this->assertSame(LoginRecord::FAILURE_TYPE_INVALID_CREDENTIALS, $savedRecord->getFailureType());
        $this->assertSame('203.0.113.195', $savedRecord->getIpAddress());
    }

    public function testDuplicateEventPrevention(): void
    {
        $repo = $this->createMock(LoginRecordRepository::class);
        $repo->expects($this->once())->method('save'); // Must be called exactly once

        $settings = $this->createSettingsService();
        $subscriber = new LoginEventSubscriber($repo, $settings, new NullLogger());

        $request = Request::create('/pimcore-studio/api/login', 'POST');
        $user = new InMemoryUser('admin', 'password');
        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn() => $user));
        $token = new UsernamePasswordToken($user, 'pimcore_studio', ['ROLE_ADMIN']);
        $authenticator = $this->createStub(JsonLoginAuthenticator::class);

        $event = new LoginSuccessEvent($authenticator, $passport, $token, $request, null, 'pimcore_studio');

        // First trigger
        $subscriber->onLoginSuccess($event);

        // Second trigger in same request lifecycle
        $subscriber->onLoginSuccess($event);
    }

    public function testDisabledTrackingDoesNotRecord(): void
    {
        $repo = $this->createMock(LoginRecordRepository::class);
        $repo->expects($this->never())->method('save');

        $settings = $this->createSettingsService([
            SettingsService::SETTING_TRACKING_ENABLED => 'false',
        ]);
        $subscriber = new LoginEventSubscriber($repo, $settings, new NullLogger());

        $request = Request::create('/pimcore-studio/api/login', 'POST');
        $user = new InMemoryUser('admin', 'password');
        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn() => $user));
        $token = new UsernamePasswordToken($user, 'pimcore_studio', ['ROLE_ADMIN']);
        $authenticator = $this->createStub(JsonLoginAuthenticator::class);

        $event = new LoginSuccessEvent($authenticator, $passport, $token, $request, null, 'pimcore_studio');
        $subscriber->onLoginSuccess($event);
    }

    public function testIpAnonymizationMode(): void
    {
        $savedRecord = null;
        $repo = $this->createStub(LoginRecordRepository::class);
        $repo->method('save')->willReturnCallback(function (LoginRecord $r) use (&$savedRecord) {
            $savedRecord = $r;
        });

        $settings = $this->createSettingsService([
            SettingsService::SETTING_IP_TRACKING_MODE => 'anonymized',
        ]);
        $subscriber = new LoginEventSubscriber($repo, $settings, new NullLogger());

        $request = Request::create('/pimcore-studio/api/login', 'POST');
        $request->server->set('REMOTE_ADDR', '192.168.1.150');
        $user = new InMemoryUser('user1', 'pass');
        $passport = new SelfValidatingPassport(new UserBadge('user1', static fn() => $user));
        $token = new UsernamePasswordToken($user, 'pimcore_studio', ['ROLE_USER']);
        $authenticator = $this->createStub(JsonLoginAuthenticator::class);

        $event = new LoginSuccessEvent($authenticator, $passport, $token, $request, null, 'pimcore_studio');
        $subscriber->onLoginSuccess($event);

        $this->assertSame('192.168.1.***', $savedRecord->getIpAddress());
        $this->assertNull($savedRecord->getRawIp());
    }

    public function testPersistenceFailureDoesNotThrowOrBlockLogin(): void
    {
        $repo = $this->createMock(LoginRecordRepository::class);
        $repo->expects($this->once())
            ->method('save')
            ->willThrowException(new \RuntimeException('Database connection lost!'));

        $settings = $this->createSettingsService();
        $subscriber = new LoginEventSubscriber($repo, $settings, new NullLogger());

        $request = Request::create('/pimcore-studio/api/login', 'POST');
        $user = new InMemoryUser('admin', 'pass');
        $passport = new SelfValidatingPassport(new UserBadge($user->getUserIdentifier(), static fn() => $user));
        $token = new UsernamePasswordToken($user, 'pimcore_studio', ['ROLE_ADMIN']);
        $authenticator = $this->createStub(JsonLoginAuthenticator::class);

        $event = new LoginSuccessEvent($authenticator, $passport, $token, $request, null, 'pimcore_studio');

        // This call MUST NOT throw an exception!
        $subscriber->onLoginSuccess($event);
        $this->assertTrue(true, 'Login completed cleanly despite persistence failure');
    }
}
