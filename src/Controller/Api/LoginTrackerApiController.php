<?php

declare(strict_types=1);

namespace Pimcore\Bundle\LoginTrackerBundle\Controller\Api;

use Pimcore\Bundle\LoginTrackerBundle\Entity\LoginRecord;
use Pimcore\Bundle\LoginTrackerBundle\PimcoreLoginTrackerBundle;
use Pimcore\Bundle\LoginTrackerBundle\Repository\LoginRecordRepository;
use Pimcore\Bundle\LoginTrackerBundle\Service\CsvExportService;
use Pimcore\Bundle\LoginTrackerBundle\Service\RetentionService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SecurityInsightsService;
use Pimcore\Bundle\LoginTrackerBundle\Service\SettingsService;
use Pimcore\Model\User as PimcoreUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/pimcore-studio/api/login-tracker')]
class LoginTrackerApiController extends AbstractController
{
    public function __construct(
        private readonly LoginRecordRepository $recordRepository,
        private readonly SettingsService $settingsService,
        private readonly SecurityInsightsService $insightsService,
        private readonly CsvExportService $csvExportService,
        private readonly RetentionService $retentionService
    ) {
    }

    /**
     * Dashboard Overview metrics & chart data.
     */
    #[Route('/overview', name: 'pimcore_login_tracker_api_overview', methods: ['GET'])]
    public function overview(Request $request): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW);

        $range = $request->query->get('range', $this->settingsService->getDefaultDateRange());
        [$from, $to] = $this->resolveDateRange($range, $request->query->get('from'), $request->query->get('to'));

        $metrics = $this->recordRepository->getDashboardMetrics($from, $to);
        $timeline = $this->recordRepository->getActivityTimeline($from, $to);

        $tz = $this->settingsService->getDisplayTimezone();

        return new JsonResponse([
            'success' => true,
            'metrics' => $metrics,
            'timeline' => $timeline,
            'range' => $range,
            'from' => $from?->format(\DateTimeInterface::ATOM),
            'to' => $to?->format(\DateTimeInterface::ATOM),
            'timezone' => $tz,
            'tracking_enabled' => $this->settingsService->isTrackingEnabled(),
        ]);
    }

    /**
     * Paginated and filtered login records.
     */
    #[Route('/records', name: 'pimcore_login_tracker_api_records', methods: ['GET'])]
    public function records(Request $request): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW);

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = max(1, min(100, (int) $request->query->get('limit', 25)));
        $sortBy = (string) $request->query->get('sortBy', 'login_time');
        $sortOrder = (string) $request->query->get('sortOrder', 'DESC');

        $filters = $this->extractFilters($request);
        $canViewSensitive = $this->hasPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW_SENSITIVE);
        $timezone = $this->settingsService->getDisplayTimezone();

        $result = $this->recordRepository->findPaginated($filters, $page, $limit, $sortBy, $sortOrder);

        $serializedItems = array_map(
            static fn(LoginRecord $record) => $record->toArray($canViewSensitive, $timezone),
            $result['items']
        );

        return new JsonResponse([
            'success' => true,
            'items' => $serializedItems,
            'total' => $result['total'],
            'page' => $result['page'],
            'limit' => $result['limit'],
            'pages' => $result['pages'],
            'timezone' => $timezone,
            'can_view_sensitive' => $canViewSensitive,
        ]);
    }

    /**
     * Single record details modal.
     */
    #[Route('/records/{id}', name: 'pimcore_login_tracker_api_record_detail', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function recordDetail(int $id): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW);

        $record = $this->recordRepository->findById($id);
        if ($record === null) {
            return new JsonResponse(['success' => false, 'message' => 'Record not found.'], Response::HTTP_NOT_FOUND);
        }

        $canViewSensitive = $this->hasPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW_SENSITIVE);
        $timezone = $this->settingsService->getDisplayTimezone();

        return new JsonResponse([
            'success' => true,
            'record' => $record->toArray($canViewSensitive, $timezone),
            'raw_ip' => $canViewSensitive ? $record->getRawIp() : null,
            'user_agent' => $canViewSensitive ? $record->getUserAgent() : null,
            'details' => $canViewSensitive ? $record->getDetails() : null,
        ]);
    }

    /**
     * User specific login summary & history.
     */
    #[Route('/user-history', name: 'pimcore_login_tracker_api_user_history', methods: ['GET'])]
    public function userHistory(Request $request): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW);

        $username = trim((string) $request->query->get('username', ''));
        if (empty($username)) {
            return new JsonResponse(['success' => false, 'message' => 'Username is required.'], Response::HTTP_BAD_REQUEST);
        }

        $summary = $this->recordRepository->getUserSummary($username);
        $history = $this->recordRepository->findUserHistory($username, 50);

        $canViewSensitive = $this->hasPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW_SENSITIVE);
        $timezone = $this->settingsService->getDisplayTimezone();

        // Check if user exists in Pimcore
        $pimcoreUser = PimcoreUser::getByName($username);
        $userInfo = null;
        if ($pimcoreUser instanceof PimcoreUser) {
            $userInfo = [
                'id' => $pimcoreUser->getId(),
                'username' => $pimcoreUser->getName(),
                'first_name' => $pimcoreUser->getFirstname(),
                'last_name' => $pimcoreUser->getLastname(),
                'email' => $pimcoreUser->getEmail(),
                'is_admin' => $pimcoreUser->isAdmin(),
                'is_active' => $pimcoreUser->isActive(),
            ];
        }

        return new JsonResponse([
            'success' => true,
            'username' => $username,
            'user_info' => $userInfo,
            'summary' => [
                'total_logins' => $summary['total_logins'],
                'successful_logins' => $summary['successful_logins'],
                'failed_logins' => $summary['failed_logins'],
                'last_success' => $summary['last_success']?->toArray($canViewSensitive, $timezone),
                'last_failed' => $summary['last_failed']?->toArray($canViewSensitive, $timezone),
            ],
            'history' => array_map(
                static fn(LoginRecord $r) => $r->toArray($canViewSensitive, $timezone),
                $history
            ),
        ]);
    }

    /**
     * Security Insights indicators.
     */
    #[Route('/insights', name: 'pimcore_login_tracker_api_insights', methods: ['GET'])]
    public function insights(): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW);

        $canViewSensitive = $this->hasPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW_SENSITIVE);
        $timezone = $this->settingsService->getDisplayTimezone();

        $data = $this->insightsService->computeInsights($canViewSensitive, $timezone);

        return new JsonResponse([
            'success' => true,
            ...$data,
        ]);
    }

    /**
     * Plugin Settings and capabilities.
     */
    #[Route('/settings', name: 'pimcore_login_tracker_api_get_settings', methods: ['GET'])]
    public function getSettings(): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW);

        return new JsonResponse([
            'success' => true,
            'settings' => $this->settingsService->getAllSettings(),
            'capabilities' => [
                'can_view_sensitive' => $this->hasPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW_SENSITIVE),
                'can_export' => $this->hasPermission(PimcoreLoginTrackerBundle::PERMISSION_EXPORT),
                'can_manage_settings' => $this->hasPermission(PimcoreLoginTrackerBundle::PERMISSION_SETTINGS),
            ],
            'total_stored_records' => $this->recordRepository->countTotal(),
        ]);
    }

    /**
     * Update Plugin Settings.
     */
    #[Route('/settings', name: 'pimcore_login_tracker_api_update_settings', methods: ['POST'])]
    public function updateSettings(Request $request): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_SETTINGS);

        $body = json_decode((string) $request->getContent(), true);
        if (!is_array($body)) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid JSON payload.'], Response::HTTP_BAD_REQUEST);
        }

        $this->settingsService->updateSettings($body);

        return new JsonResponse([
            'success' => true,
            'message' => 'Settings saved successfully.',
            'settings' => $this->settingsService->getAllSettings(),
        ]);
    }

    /**
     * Trigger immediate retention purge.
     */
    #[Route('/cleanup', name: 'pimcore_login_tracker_api_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_SETTINGS);

        $body = json_decode((string) $request->getContent(), true);
        $days = isset($body['days']) ? (int) $body['days'] : null;

        $deleted = $this->retentionService->purgeExpiredRecords($days);

        return new JsonResponse([
            'success' => true,
            'message' => sprintf('%d expired login record(s) purged successfully.', $deleted),
            'deleted_count' => $deleted,
            'remaining_count' => $this->recordRepository->countTotal(),
        ]);
    }

    /**
     * Export login activity records as CSV.
     */
    #[Route('/export', name: 'pimcore_login_tracker_api_export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_EXPORT);

        $filters = $this->extractFilters($request);
        $canViewSensitive = $this->hasPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW_SENSITIVE);
        $timezone = $this->settingsService->getDisplayTimezone();

        return $this->csvExportService->createStreamedExport($filters, $canViewSensitive, $timezone);
    }

    /**
     * Distinct usernames for auto-complete.
     */
    #[Route('/users-list', name: 'pimcore_login_tracker_api_users_list', methods: ['GET'])]
    public function usersList(): JsonResponse
    {
        $this->checkPermission(PimcoreLoginTrackerBundle::PERMISSION_VIEW);

        $usernames = $this->recordRepository->getDistinctUsernames();

        return new JsonResponse([
            'success' => true,
            'users' => $usernames,
        ]);
    }

    private function extractFilters(Request $request): array
    {
        $filters = [];

        if ($request->query->has('date_from') && !empty($request->query->get('date_from'))) {
            $filters['date_from'] = $request->query->get('date_from');
        }
        if ($request->query->has('date_to') && !empty($request->query->get('date_to'))) {
            $filters['date_to'] = $request->query->get('date_to');
        }
        if ($request->query->has('username') && !empty($request->query->get('username'))) {
            $filters['username'] = $request->query->get('username');
        }
        if ($request->query->has('user_id') && !empty($request->query->get('user_id'))) {
            $filters['user_id'] = (int) $request->query->get('user_id');
        }
        if ($request->query->has('status') && !empty($request->query->get('status'))) {
            $filters['status'] = $request->query->get('status');
        }
        if ($request->query->has('auth_method') && !empty($request->query->get('auth_method'))) {
            $filters['auth_method'] = $request->query->get('auth_method');
        }
        if ($request->query->has('ip_address') && !empty($request->query->get('ip_address'))) {
            $filters['ip_address'] = $request->query->get('ip_address');
        }
        if ($request->query->has('search') && !empty($request->query->get('search'))) {
            $filters['search'] = $request->query->get('search');
        }

        return $filters;
    }

    /**
     * @return array{0: ?\DateTimeInterface, 1: ?\DateTimeInterface}
     */
    private function resolveDateRange(string $preset, ?string $customFrom = null, ?string $customTo = null): array
    {
        if (!empty($customFrom) && !empty($customTo)) {
            try {
                return [new \DateTimeImmutable($customFrom), new \DateTimeImmutable($customTo)];
            } catch (\Throwable) {
                // fallback
            }
        }

        $now = new \DateTimeImmutable();
        return match ($preset) {
            '24_hours' => [$now->modify('-24 hours'), $now],
            '30_days' => [$now->modify('-30 days'), $now],
            '90_days' => [$now->modify('-90 days'), $now],
            default => [$now->modify('-7 days'), $now], // '7_days'
        };
    }

    private function checkPermission(string $permission): void
    {
        $user = $this->getUser();
        if ($user === null) {
            throw new AccessDeniedException('You must be logged in to access Login Tracker.');
        }

        $pimcoreUser = null;
        if ($user instanceof \Pimcore\Security\User\User) {
            $pimcoreUser = $user->getUser();
        } elseif ($user instanceof PimcoreUser) {
            $pimcoreUser = $user;
        } elseif (method_exists($user, 'getUser')) {
            $pimcoreUser = $user->getUser();
        }

        $targetUser = $pimcoreUser ?? $user;
        if (method_exists($targetUser, 'isAdmin') && $targetUser->isAdmin()) {
            return;
        }
        if (method_exists($targetUser, 'isAllowed') && $targetUser->isAllowed($permission)) {
            return;
        }

        if ($this->isGranted($permission)) {
            return;
        }

        throw new AccessDeniedException(sprintf('Access denied. Missing permission: %s', $permission));
    }

    private function hasPermission(string $permission): bool
    {
        try {
            $this->checkPermission($permission);
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
