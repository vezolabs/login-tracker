<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\EventSubscriber;

use Pimcore\Bundle\LoginTrackerBundle\Entity\LoginRecord;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;
use Pimcore\Bundle\LoginTrackerBundle\Service\UserAgentParser;
use Pimcore\Model\User as PimcoreUser;
use Pimcore\Security\User\User as SecurityUser;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccountStatusException;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

final class LoginEventSubscriber implements EventSubscriberInterface
{
    private const string ATTRIBUTE_HANDLED = '_pimcore_login_tracker_handled';

    public function __construct(
        private readonly LoginRecordRepository $repository,
        private readonly SettingsService $settingsService,
        private readonly ?LoggerInterface $logger = null
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => ['onLoginSuccess', 10],
            LoginFailureEvent::class => ['onLoginFailure', 10],
        ];
    }

    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->isAlreadyHandled($request)) {
            return;
        }

        try {
            if (!$this->settingsService->isTrackingEnabled() || !$this->settingsService->isTrackSuccessfulEnabled()) {
                return;
            }

            $user = $event->getAuthenticatedToken()->getUser();
            $username = $user->getUserIdentifier();
            $userId = null;
            $displayName = null;

            if ($user instanceof SecurityUser) {
                $pimcoreUser = $user->getUser();
                $userId = $pimcoreUser->getId();
                $displayName = $this->resolveDisplayName($pimcoreUser);
            } elseif ($user instanceof PimcoreUser) {
                $userId = $user->getId();
                $displayName = $this->resolveDisplayName($user);
            } else {
                // Try looking up Pimcore user by username if environment permits
                try {
                    $pimcoreUser = PimcoreUser::getByName($username);
                    if ($pimcoreUser instanceof PimcoreUser) {
                        $userId = $pimcoreUser->getId();
                        $displayName = $this->resolveDisplayName($pimcoreUser);
                    }
                } catch (\Throwable) {
                    // Ignore DB lookup failure when database is uninitialized or not connected
                }
            }

            [$authMethod, $authLabel] = $this->resolveAuthMethod($event->getAuthenticator());
            [$ipAddress, $rawIp] = $this->resolveIp($request);
            [$browser, $os, $deviceType, $userAgent] = $this->resolveUserAgent($request);

            $record = new LoginRecord();
            $record->setLoginTime(new \DateTimeImmutable());
            $record->setUsername($username);
            $record->setUserId($userId);
            $record->setDisplayName($displayName);
            $record->setStatus(LoginRecord::STATUS_SUCCESS);
            $record->setAuthMethod($authMethod);
            $record->setAuthMethodLabel($authLabel);
            $record->setIpAddress($ipAddress);
            $record->setRawIp($rawIp);
            $record->setUserAgent($userAgent);
            $record->setBrowser($browser);
            $record->setOs($os);
            $record->setDeviceType($deviceType);
            $record->setFirewall($event->getFirewallName());
            $record->setRoute((string) $request->attributes->get('_route'));

            $this->repository->save($record);
            $this->markAsHandled($request);
        } catch (\Throwable $e) {
            $this->logger?->error('[LoginTracker] Failed to record login success: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();
        if ($this->isAlreadyHandled($request)) {
            return;
        }

        try {
            if (!$this->settingsService->isTrackingEnabled() || !$this->settingsService->isTrackFailedEnabled()) {
                return;
            }

            $exception = $event->getException();
            $username = $this->resolveFailureUsername($event, $request);
            $userId = null;
            $displayName = null;

            if ($username !== 'unknown') {
                try {
                    $pimcoreUser = PimcoreUser::getByName($username);
                    if ($pimcoreUser instanceof PimcoreUser) {
                        $userId = $pimcoreUser->getId();
                        $displayName = $this->resolveDisplayName($pimcoreUser);
                    }
                } catch (\Throwable) {
                    // Ignore DB lookup failure when database is uninitialized
                }
            }

            [$failureReason, $failureType] = $this->resolveFailureReason($exception);
            [$authMethod, $authLabel] = $this->resolveAuthMethod($event->getAuthenticator());
            [$ipAddress, $rawIp] = $this->resolveIp($request);
            [$browser, $os, $deviceType, $userAgent] = $this->resolveUserAgent($request);

            $record = new LoginRecord();
            $record->setLoginTime(new \DateTimeImmutable());
            $record->setUsername($username);
            $record->setUserId($userId);
            $record->setDisplayName($displayName);
            $record->setStatus(LoginRecord::STATUS_FAILED);
            $record->setAuthMethod($authMethod);
            $record->setAuthMethodLabel($authLabel);
            $record->setIpAddress($ipAddress);
            $record->setRawIp($rawIp);
            $record->setUserAgent($userAgent);
            $record->setBrowser($browser);
            $record->setOs($os);
            $record->setDeviceType($deviceType);
            $record->setFailureReason($failureReason);
            $record->setFailureType($failureType);
            $record->setFirewall($event->getFirewallName());
            $record->setRoute((string) $request->attributes->get('_route'));

            $this->repository->save($record);
            $this->markAsHandled($request);
        } catch (\Throwable $e) {
            $this->logger?->error('[LoginTracker] Failed to record login failure: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    private function isAlreadyHandled(Request $request): bool
    {
        return $request->attributes->getBoolean(self::ATTRIBUTE_HANDLED, false);
    }

    private function markAsHandled(Request $request): void
    {
        $request->attributes->set(self::ATTRIBUTE_HANDLED, true);
    }

    private function resolveDisplayName(PimcoreUser $user): string
    {
        $first = (string) $user->getFirstname();
        $last = (string) $user->getLastname();
        $name = trim($first . ' ' . $last);

        return !empty($name) ? $name : (string) $user->getName();
    }

    /**
     * @return array{0: string, 1: string} [authMethod, authMethodLabel]
     */
    private function resolveAuthMethod(AuthenticatorInterface $authenticator): array
    {
        $class = get_class($authenticator);

        if (str_contains($class, 'JsonLoginAuthenticator')) {
            return [LoginRecord::AUTH_METHOD_PASSWORD, 'Password / Credentials'];
        }
        if (str_contains($class, 'AdminTokenAuthenticator') || str_contains($class, 'Token')) {
            return [LoginRecord::AUTH_METHOD_TOKEN, 'Studio Admin Token'];
        }
        if (str_contains($class, 'Sso') || str_contains($class, 'Saml') || str_contains($class, 'Entra')) {
            return [LoginRecord::AUTH_METHOD_SSO, 'Single Sign-On (SAML)'];
        }

        $parts = explode('\\', $class);
        $short = end($parts);
        $clean = str_replace('Authenticator', '', $short);

        return [LoginRecord::AUTH_METHOD_OTHER, $clean ?: 'Other'];
    }

    /**
     * @return array{0: ?string, 1: ?string} [ipAddress, rawIp]
     */
    private function resolveIp(Request $request): array
    {
        $mode = $this->settingsService->getIpTrackingMode();
        if ($mode === SettingsService::IP_MODE_DISABLED) {
            return [null, null];
        }

        $rawIp = $request->getClientIp();
        if (empty($rawIp)) {
            return [null, null];
        }

        if ($mode === SettingsService::IP_MODE_ANONYMIZED) {
            return [LoginRecord::maskIpAddress($rawIp), null];
        }

        // Full mode
        return [$rawIp, $rawIp];
    }

    /**
     * @return array{0: ?string, 1: ?string, 2: ?string, 3: ?string} [browser, os, deviceType, userAgent]
     */
    private function resolveUserAgent(Request $request): array
    {
        if (!$this->settingsService->isRecordUserAgentEnabled()) {
            return [null, null, null, null];
        }

        $ua = (string) $request->headers->get('User-Agent');
        if (empty($ua)) {
            return ['Unknown Browser', 'Unknown OS', 'desktop', null];
        }

        $parsed = UserAgentParser::parse($ua);

        return [
            $parsed['browser'],
            $parsed['os'],
            $parsed['device_type'],
            $ua,
        ];
    }

    private function resolveFailureUsername(LoginFailureEvent $event, Request $request): string
    {
        $passport = $event->getPassport();
        if ($passport !== null && $passport->hasBadge(UserBadge::class)) {
            /** @var UserBadge $badge */
            $badge = $passport->getBadge(UserBadge::class);
            $identifier = $badge->getUserIdentifier();
            if (!empty($identifier)) {
                return $identifier;
            }
        }

        // Check request body for JSON login
        $content = (string) $request->getContent();
        if (!empty($content)) {
            $json = json_decode($content, true);
            if (is_array($json) && !empty($json['username'])) {
                return (string) $json['username'];
            }
        }

        // Form post or query parameter
        $param = $request->request->get('_username')
            ?? $request->request->get('username')
            ?? $request->query->get('username');

        if (!empty($param)) {
            return (string) $param;
        }

        return 'unknown';
    }

    /**
     * @return array{0: string, 1: string} [failureReason, failureType]
     */
    private function resolveFailureReason(AuthenticationException $exception): array
    {
        $key = $exception->getMessageKey();

        if ($exception instanceof BadCredentialsException || str_contains($key, 'Invalid credentials')) {
            return ['Invalid username or password', LoginRecord::FAILURE_TYPE_INVALID_CREDENTIALS];
        }

        if ($exception instanceof UserNotFoundException || str_contains($key, 'could not be found')) {
            return ['User account does not exist', LoginRecord::FAILURE_TYPE_USER_NOT_FOUND];
        }

        if ($exception instanceof AccountStatusException || str_contains($key, 'disabled') || str_contains($key, 'locked')) {
            return ['Account is locked or disabled', LoginRecord::FAILURE_TYPE_ACCOUNT_DISABLED];
        }

        if (str_contains($key, 'token') || str_contains($key, 'expired')) {
            return ['Authentication token expired or invalid', LoginRecord::FAILURE_TYPE_TOKEN_INVALID];
        }

        return ['Authentication failed', LoginRecord::FAILURE_TYPE_UNKNOWN];
    }
}
