<?php

declare(strict_types=1);

final class DashboardService
{
    public static function getData(PDO $pdo, int $days = 7): array
    {
        $days = max(7, min(30, $days));

        $start = microtime(true);
        $pdo->query('SELECT 1');
        $dbMs = (int) round((microtime(true) - $start) * 1000);

        $counts = AdminService::counts($pdo);
        $storage = self::storageUsage();

        return [
            'counts' => $counts,
            'chart' => self::chartSeries($pdo, $days),
            'recentActivity' => self::recentActivity($pdo),
            'topCategories' => self::topCategories($pdo),
            'topApis' => self::topApis($pdo),
            'system' => [
                'server' => 'Online',
                'database' => $dbMs < 500 ? 'Healthy' : 'Slow',
                'databaseMs' => $dbMs,
                'storagePercent' => $storage['percent'],
                'storageLabel' => $storage['label'],
                'apiResponseMs' => self::apiResponseMs(),
            ],
            'unverifiedUsers' => self::unverifiedUserCount($pdo),
            'updatedAt' => date('c'),
        ];
    }

    private static function chartSeries(PDO $pdo, int $days = 7): array
    {
        $dayList = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $dayList[] = (new DateTimeImmutable("-{$i} days"))->format('Y-m-d');
        }

        $users = self::countsByDay($pdo, 'app_users', $days);
        $apis = self::countsByDay($pdo, 'api_listings', $days);
        $keys = self::countsByDay($pdo, 'api_purchases', $days);

        $labels = array_map(static fn (string $day) => (new DateTimeImmutable($day))->format('M d'), $dayList);

        return [
            'labels' => $labels,
            'users' => array_map(static fn (string $day) => $users[$day] ?? 0, $dayList),
            'apis' => array_map(static fn (string $day) => $apis[$day] ?? 0, $dayList),
            'keys' => array_map(static fn (string $day) => $keys[$day] ?? 0, $dayList),
        ];
    }

    private static function countsByDay(PDO $pdo, string $table, int $days): array
    {
        $stmt = $pdo->prepare(
            "SELECT DATE(created_at) AS day, COUNT(*)::int AS total
             FROM {$table}
             WHERE created_at >= CURRENT_DATE - ?::integer
             GROUP BY DATE(created_at)
             ORDER BY day"
        );
        $stmt->execute([$days - 1]);

        $map = [];
        foreach ($stmt->fetchAll() as $row) {
            $map[(string) $row['day']] = (int) $row['total'];
        }

        return $map;
    }

    private static function recentActivity(PDO $pdo): array
    {
        $events = [];

        $users = $pdo->query(
            'SELECT full_name, email, created_at FROM app_users ORDER BY created_at DESC LIMIT 8'
        )->fetchAll();
        foreach ($users as $row) {
            $events[] = [
                'type' => 'user',
                'title' => 'New user registered',
                'detail' => $row['email'],
                'at' => $row['created_at'],
            ];
        }

        $purchases = $pdo->query(
            'SELECT u.email, a.name AS api_name, p.created_at
             FROM api_purchases p
             JOIN app_users u ON u.id = p.user_id
             JOIN api_listings a ON a.id = p.api_listing_id
             ORDER BY p.created_at DESC
             LIMIT 8'
        )->fetchAll();
        foreach ($purchases as $row) {
            $events[] = [
                'type' => 'key',
                'title' => 'API key generated',
                'detail' => $row['email'] . ' · ' . $row['api_name'],
                'at' => $row['created_at'],
            ];
        }

        $listings = $pdo->query(
            'SELECT name, created_at FROM api_listings ORDER BY created_at DESC LIMIT 8'
        )->fetchAll();
        foreach ($listings as $row) {
            $events[] = [
                'type' => 'api',
                'title' => 'API listing added',
                'detail' => $row['name'],
                'at' => $row['created_at'],
            ];
        }

        $admins = $pdo->query(
            'SELECT username, created_at FROM admin_users ORDER BY created_at DESC LIMIT 4'
        )->fetchAll();
        foreach ($admins as $row) {
            $events[] = [
                'type' => 'admin',
                'title' => 'Admin account activity',
                'detail' => $row['username'],
                'at' => $row['created_at'],
            ];
        }

        usort($events, static fn (array $a, array $b) => strtotime((string) $b['at']) <=> strtotime((string) $a['at']));

        return array_map(static function (array $event) {
            $event['timeAgo'] = time_ago((string) $event['at']);
            return $event;
        }, array_slice($events, 0, 8));
    }

    private static function topCategories(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT c.id, c.name, COUNT(a.id)::int AS api_count
             FROM categories c
             LEFT JOIN api_listings a ON a.category_id = c.id
             GROUP BY c.id, c.name
             ORDER BY api_count DESC, c.name ASC
             LIMIT 5'
        );

        return $stmt->fetchAll();
    }

    private static function topApis(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT a.id, a.name, COUNT(p.id)::int AS purchase_count
             FROM api_listings a
             LEFT JOIN api_purchases p ON p.api_listing_id = a.id
             GROUP BY a.id, a.name
             ORDER BY purchase_count DESC, a.name ASC
             LIMIT 5'
        );

        return $stmt->fetchAll();
    }

    private static function unverifiedUserCount(PDO $pdo): int
    {
        return (int) $pdo->query('SELECT COUNT(*) FROM app_users WHERE email_verified IS NOT TRUE')->fetchColumn();
    }

    private static function storageUsage(): array
    {
        $path = dirname(__DIR__);
        $free = @disk_free_space($path);
        $total = @disk_total_space($path);

        if ($free === false || $total === false || $total <= 0) {
            return ['percent' => 0, 'label' => 'N/A'];
        }

        $usedPercent = (int) round((($total - $free) / $total) * 100);

        return [
            'percent' => min(100, max(0, $usedPercent)),
            'label' => $usedPercent . '% Used',
        ];
    }

    private static function apiResponseMs(): int
    {
        $start = microtime(true);
        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
            . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
            . '/api/public/apis';

        $context = stream_context_create([
            'http' => [
                'timeout' => 5,
                'ignore_errors' => true,
            ],
        ]);

        @file_get_contents($url, false, $context);
        $elapsed = (int) round((microtime(true) - $start) * 1000);

        return max($elapsed, 1);
    }
}

function time_ago(string $datetime): string
{
    $timestamp = strtotime($datetime);
    if ($timestamp === false) {
        return 'recently';
    }

    $diff = time() - $timestamp;
    if ($diff < 60) {
        return 'just now';
    }
    if ($diff < 3600) {
        $mins = (int) floor($diff / 60);
        return $mins . 'm ago';
    }
    if ($diff < 86400) {
        $hours = (int) floor($diff / 3600);
        return $hours . 'h ago';
    }
    $days = (int) floor($diff / 86400);
    return $days . 'd ago';
}
