<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;

/**
 * Real SQL-driven KPIs. Heavy aggregations are cached into daily_metrics /
 * kpi_snapshots by cron/analytics.php instead of being recomputed per page load.
 */
final class AnalyticsService
{
    private Database $db;

    public function __construct(?Database $db = null)
    {
        $this->db = $db ?? Database::instance();
    }

    private function branchClause(?int $branchId, string $alias = ''): array
    {
        $p = $alias !== '' ? $alias . '.' : '';
        return $branchId ? [" AND {$p}branch_id = :branch", ['branch' => $branchId]] : ['', []];
    }

    /** Dashboard KPI block. */
    public function dashboardKpis(?int $branchId = null): array
    {
        [$bc, $bp] = $this->branchClause($branchId, 'i');
        $today     = date('Y-m-d');
        $yesterday = date('Y-m-d', strtotime('-1 day'));

        $revenueToday = (float)$this->db->scalar(
            "SELECT COALESCE(SUM(i.total - i.refunded_amount),0) FROM invoices i
             WHERE i.issue_date = :d AND i.status <> 'CANCELLED'{$bc}",
            array_merge(['d' => $today], $bp)
        );
        $revenueYesterday = (float)$this->db->scalar(
            "SELECT COALESCE(SUM(i.total - i.refunded_amount),0) FROM invoices i
             WHERE i.issue_date = :d AND i.status <> 'CANCELLED'{$bc}",
            array_merge(['d' => $yesterday], $bp)
        );

        [$abc, $abp] = $this->branchClause($branchId, 'a');
        $appointmentsToday = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM appointments a WHERE a.appointment_date = :d AND a.status <> 'CANCELLED'{$abc}",
            array_merge(['d' => $today], $abp)
        );
        $noShowToday = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM appointments a WHERE a.appointment_date = :d AND a.status = 'NO_SHOW'{$abc}",
            array_merge(['d' => $today], $abp)
        );

        $newCustomers = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM customers WHERE DATE(created_at) = :d AND deleted_at IS NULL',
            ['d' => $today]
        );
        $returning = (int)$this->db->scalar(
            "SELECT COUNT(DISTINCT a.customer_id) FROM appointments a
             JOIN customers c ON c.id = a.customer_id
             WHERE a.appointment_date = :d AND c.visits_count > 1 AND a.status <> 'CANCELLED'{$abc}",
            array_merge(['d' => $today], $abp)
        );

        $avgTicket = (float)$this->db->scalar(
            "SELECT COALESCE(AVG(i.total),0) FROM invoices i
             WHERE i.issue_date >= :d AND i.status <> 'CANCELLED'{$bc}",
            array_merge(['d' => date('Y-m-d', strtotime('-30 days'))], $bp)
        );

        $monthRevenue = (float)$this->db->scalar(
            "SELECT COALESCE(SUM(i.total - i.refunded_amount),0) FROM invoices i
             WHERE i.issue_date >= :d AND i.status <> 'CANCELLED'{$bc}",
            array_merge(['d' => date('Y-m-01')], $bp)
        );
        $unpaid = (float)$this->db->scalar(
            "SELECT COALESCE(SUM(i.due_amount),0) FROM invoices i
             WHERE i.payment_status IN ('UNPAID','PARTIAL') AND i.status <> 'CANCELLED'{$bc}",
            $bp
        );

        return [
            'revenue_today'      => $revenueToday,
            'revenue_change'     => $revenueYesterday > 0 ? round(($revenueToday - $revenueYesterday) / $revenueYesterday * 100, 1) : null,
            'appointments_today' => $appointmentsToday,
            'new_customers'      => $newCustomers,
            'returning_customers' => $returning,
            'average_ticket'     => round($avgTicket, 0),
            'no_show_today'      => $noShowToday,
            'month_revenue'      => $monthRevenue,
            'unpaid_total'       => $unpaid,
        ];
    }

    /** Daily revenue series for charts. */
    public function revenueSeries(int $days = 30, ?int $branchId = null): array
    {
        [$bc, $bp] = $this->branchClause($branchId, 'i');
        $rows = $this->db->select(
            "SELECT i.issue_date AS d, SUM(i.total - i.refunded_amount) AS revenue, COUNT(*) AS invoices
             FROM invoices i
             WHERE i.issue_date >= :from AND i.status <> 'CANCELLED'{$bc}
             GROUP BY i.issue_date ORDER BY i.issue_date",
            array_merge(['from' => date('Y-m-d', strtotime("-{$days} days"))], $bp)
        );
        return $this->fillDateSeries($rows, $days, 'revenue');
    }

    public function appointmentSeries(int $days = 30, ?int $branchId = null): array
    {
        [$bc, $bp] = $this->branchClause($branchId, 'a');
        $rows = $this->db->select(
            "SELECT a.appointment_date AS d, COUNT(*) AS total,
                    SUM(CASE WHEN a.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN a.status = 'NO_SHOW' THEN 1 ELSE 0 END) AS no_show
             FROM appointments a WHERE a.appointment_date >= :from{$bc}
             GROUP BY a.appointment_date ORDER BY a.appointment_date",
            array_merge(['from' => date('Y-m-d', strtotime("-{$days} days"))], $bp)
        );
        return $this->fillDateSeries($rows, $days, 'total');
    }

    private function fillDateSeries(array $rows, int $days, string $valueKey): array
    {
        $byDate = [];
        foreach ($rows as $r) {
            $byDate[(string)$r['d']] = $r;
        }
        $out = [];
        for ($i = $days; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $row  = $byDate[$date] ?? [];
            $out[] = [
                'date'  => $date,
                'value' => (float)($row[$valueKey] ?? 0),
                'extra' => $row,
            ];
        }
        return $out;
    }

    public function topServices(int $limit = 8, ?int $branchId = null, int $days = 30): array
    {
        [$bc, $bp] = $this->branchClause($branchId, 'i');
        return $this->db->select(
            "SELECT sv.name, sc.name AS category, COUNT(*) AS times, SUM(ii.total) AS revenue
             FROM invoice_items ii
             JOIN invoices i  ON i.id = ii.invoice_id
             JOIN services sv ON sv.id = ii.service_id
             JOIN service_categories sc ON sc.id = sv.category_id
             WHERE ii.item_type = 'SERVICE' AND i.issue_date >= :from AND i.status <> 'CANCELLED'{$bc}
             GROUP BY sv.id, sv.name, sc.name ORDER BY revenue DESC LIMIT {$limit}",
            array_merge(['from' => date('Y-m-d', strtotime("-{$days} days"))], $bp)
        );
    }

    public function staffPerformance(int $limit = 8, ?int $branchId = null, int $days = 30): array
    {
        [$bc, $bp] = $this->branchClause($branchId, 'i');
        return $this->db->select(
            "SELECT s.id, CONCAT(s.first_name,' ',s.last_name) AS name, s.color, s.rating,
                    COALESCE(SUM(ii.total),0) AS revenue, COUNT(ii.id) AS services
             FROM staff s
             LEFT JOIN invoice_items ii ON ii.staff_id = s.id
             LEFT JOIN invoices i ON i.id = ii.invoice_id AND i.issue_date >= :from AND i.status <> 'CANCELLED'{$bc}
             WHERE s.deleted_at IS NULL AND s.status = 'ACTIVE'
             GROUP BY s.id, s.first_name, s.last_name, s.color, s.rating
             ORDER BY revenue DESC LIMIT {$limit}",
            array_merge(['from' => date('Y-m-d', strtotime("-{$days} days"))], $bp)
        );
    }

    public function branchPerformance(int $days = 30): array
    {
        return $this->db->select(
            "SELECT b.id, b.name, COALESCE(SUM(i.total - i.refunded_amount),0) AS revenue,
                    COUNT(DISTINCT i.id) AS invoices,
                    (SELECT COUNT(*) FROM appointments a WHERE a.branch_id = b.id AND a.appointment_date >= :from2) AS appointments
             FROM branches b
             LEFT JOIN invoices i ON i.branch_id = b.id AND i.issue_date >= :from AND i.status <> 'CANCELLED'
             WHERE b.deleted_at IS NULL
             GROUP BY b.id, b.name ORDER BY revenue DESC",
            ['from' => date('Y-m-d', strtotime("-{$days} days")), 'from2' => date('Y-m-d', strtotime("-{$days} days"))]
        );
    }

    /** Retention, churn, CLV, occupancy, repeat rate. */
    public function businessMetrics(?int $branchId = null): array
    {
        $totalCustomers = (int)$this->db->scalar('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL');
        $repeat         = (int)$this->db->scalar('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND visits_count > 1');
        $active90       = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND last_visit_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)'
        );
        $churned        = (int)$this->db->scalar(
            'SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL AND visits_count > 0
             AND (last_visit_at IS NULL OR last_visit_at < DATE_SUB(NOW(), INTERVAL 180 DAY))'
        );
        $clv = (float)$this->db->scalar(
            'SELECT COALESCE(AVG(total_spent),0) FROM customers WHERE deleted_at IS NULL AND visits_count > 0'
        );

        [$abc, $abp] = $this->branchClause($branchId, 'a');
        $appointments = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM appointments a WHERE a.appointment_date >= :from{$abc}",
            array_merge(['from' => date('Y-m-d', strtotime('-30 days'))], $abp)
        );
        $noShows = (int)$this->db->scalar(
            "SELECT COUNT(*) FROM appointments a WHERE a.appointment_date >= :from AND a.status = 'NO_SHOW'{$abc}",
            array_merge(['from' => date('Y-m-d', strtotime('-30 days'))], $abp)
        );
        $bookedMinutes = (int)$this->db->scalar(
            "SELECT COALESCE(SUM(a.duration_minutes),0) FROM appointments a
             WHERE a.appointment_date >= :from AND a.status NOT IN ('CANCELLED','NO_SHOW'){$abc}",
            array_merge(['from' => date('Y-m-d', strtotime('-30 days'))], $abp)
        );
        $staffCount    = max(1, (int)$this->db->scalar("SELECT COUNT(*) FROM staff WHERE status = 'ACTIVE' AND deleted_at IS NULL"));
        $capacity      = $staffCount * 30 * 8 * 60; // 30 days * 8h
        $occupancy     = $capacity > 0 ? round($bookedMinutes / $capacity * 100, 1) : 0.0;

        return [
            'total_customers' => $totalCustomers,
            'repeat_rate'     => $totalCustomers > 0 ? round($repeat / $totalCustomers * 100, 1) : 0.0,
            'retention_rate'  => $totalCustomers > 0 ? round($active90 / $totalCustomers * 100, 1) : 0.0,
            'churn_rate'      => $totalCustomers > 0 ? round($churned / $totalCustomers * 100, 1) : 0.0,
            'clv'             => round($clv, 0),
            'occupancy'       => $occupancy,
            'no_show_rate'    => $appointments > 0 ? round($noShows / $appointments * 100, 1) : 0.0,
            'active_90d'      => $active90,
            'churned'         => $churned,
        ];
    }

    /** Customers needing attention (dashboard alerts). */
    public function customerAlerts(int $limit = 8): array
    {
        return $this->db->select(
            "SELECT c.id, c.first_name, c.last_name, c.mobile, c.last_visit_at, c.total_spent, c.churn_risk,
                    DATEDIFF(NOW(), c.last_visit_at) AS days_since
             FROM customers c
             WHERE c.deleted_at IS NULL AND c.status = 'ACTIVE' AND c.visits_count > 0
               AND c.last_visit_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
             ORDER BY c.total_spent DESC LIMIT {$limit}"
        );
    }

    /* --------------------- scheduled aggregations --------------------- */

    public function aggregateDaily(string $date, ?int $branchId = null): void
    {
        [$bc, $bp] = $this->branchClause($branchId, 'i');
        $inv = $this->db->selectOne(
            "SELECT COALESCE(SUM(i.total - i.refunded_amount),0) AS revenue, COUNT(*) AS cnt,
                    COALESCE(AVG(i.total),0) AS avg_ticket
             FROM invoices i WHERE i.issue_date = :d AND i.status <> 'CANCELLED'{$bc}",
            array_merge(['d' => $date], $bp)
        ) ?? [];

        [$abc, $abp] = $this->branchClause($branchId, 'a');
        $app = $this->db->selectOne(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN a.status = 'COMPLETED' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN a.status = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled,
                    SUM(CASE WHEN a.status = 'NO_SHOW'   THEN 1 ELSE 0 END) AS no_show
             FROM appointments a WHERE a.appointment_date = :d{$abc}",
            array_merge(['d' => $date], $abp)
        ) ?? [];

        $newCustomers = (int)$this->db->scalar('SELECT COUNT(*) FROM customers WHERE DATE(created_at) = :d', ['d' => $date]);
        $returning    = (int)$this->db->scalar(
            "SELECT COUNT(DISTINCT a.customer_id) FROM appointments a JOIN customers c ON c.id = a.customer_id
             WHERE a.appointment_date = :d AND c.visits_count > 1{$abc}",
            array_merge(['d' => $date], $abp)
        );

        $existing = $this->db->selectOne(
            'SELECT id FROM daily_metrics WHERE metric_date = :d AND ' . ($branchId ? 'branch_id = :b' : 'branch_id IS NULL'),
            $branchId ? ['d' => $date, 'b' => $branchId] : ['d' => $date]
        );
        $payload = [
            'metric_date'         => $date,
            'branch_id'           => $branchId,
            'revenue'             => number_format((float)($inv['revenue'] ?? 0), 2, '.', ''),
            'invoices_count'      => (int)($inv['cnt'] ?? 0),
            'appointments_count'  => (int)($app['total'] ?? 0),
            'completed_count'     => (int)($app['completed'] ?? 0),
            'cancelled_count'     => (int)($app['cancelled'] ?? 0),
            'no_show_count'       => (int)($app['no_show'] ?? 0),
            'new_customers'       => $newCustomers,
            'returning_customers' => $returning,
            'average_ticket'      => number_format((float)($inv['avg_ticket'] ?? 0), 2, '.', ''),
            'computed_at'         => date('Y-m-d H:i:s'),
        ];
        if ($existing !== null) {
            unset($payload['metric_date'], $payload['branch_id']);
            $this->db->update('daily_metrics', $payload, 'id = :id', ['id' => (int)$existing['id']]);
        } else {
            $this->db->insert('daily_metrics', $payload);
        }
    }

    public function snapshotKpis(?int $branchId = null): void
    {
        $metrics = $this->businessMetrics($branchId);
        foreach ($metrics as $key => $value) {
            if (!is_numeric($value)) {
                continue;
            }
            $this->db->insert('kpi_snapshots', [
                'kpi_key'   => $key,
                'kpi_value' => number_format((float)$value, 4, '.', ''),
                'period'    => date('Y-m-d'),
                'branch_id' => $branchId,
            ]);
        }
    }

    /** RFM segmentation for all customers. */
    public function computeRfm(): int
    {
        $customers = $this->db->select(
            'SELECT id, visits_count, total_spent, last_visit_at FROM customers
             WHERE deleted_at IS NULL AND visits_count > 0'
        );
        $count = 0;
        foreach ($customers as $c) {
            $recencyDays = $c['last_visit_at'] ? (int)((time() - strtotime((string)$c['last_visit_at'])) / 86400) : 999;
            $r = $recencyDays <= 30 ? 5 : ($recencyDays <= 60 ? 4 : ($recencyDays <= 120 ? 3 : ($recencyDays <= 240 ? 2 : 1)));
            $f = (int)$c['visits_count'] >= 12 ? 5 : ((int)$c['visits_count'] >= 8 ? 4 : ((int)$c['visits_count'] >= 4 ? 3 : ((int)$c['visits_count'] >= 2 ? 2 : 1)));
            $spent = (float)$c['total_spent'];
            $m = $spent >= 20000000 ? 5 : ($spent >= 10000000 ? 4 : ($spent >= 5000000 ? 3 : ($spent >= 1000000 ? 2 : 1)));

            $segment = match (true) {
                $r >= 4 && $f >= 4 && $m >= 4 => 'CHAMPION',
                $r >= 4 && $f >= 3            => 'LOYAL',
                $r >= 4 && $f <= 2            => 'NEW',
                $r <= 2 && $f >= 4            => 'AT_RISK',
                $r <= 2 && $f <= 2            => 'LOST',
                default                       => 'POTENTIAL',
            };

            $existing = $this->db->scalar('SELECT id FROM rfm_segments WHERE customer_id = :c', ['c' => (int)$c['id']]);
            $payload  = [
                'recency' => $recencyDays, 'frequency' => (int)$c['visits_count'],
                'monetary' => number_format($spent, 2, '.', ''),
                'r_score' => $r, 'f_score' => $f, 'm_score' => $m, 'segment' => $segment,
                'computed_at' => date('Y-m-d H:i:s'),
            ];
            if ($existing) {
                $this->db->update('rfm_segments', $payload, 'id = :id', ['id' => (int)$existing]);
            } else {
                $this->db->insert('rfm_segments', array_merge(['customer_id' => (int)$c['id']], $payload));
            }

            // Health score & churn risk denormalized onto the customer row.
            $health = (int)min(100, round(($r + $f + $m) / 15 * 100));
            $churn  = round(max(0, 100 - $health) * 0.9, 2);
            $this->db->update('customers', ['health_score' => $health, 'churn_risk' => $churn], 'id = :c', ['c' => (int)$c['id']]);
            $this->db->insert('customer_health_scores', [
                'customer_id' => (int)$c['id'], 'score' => $health,
                'recency_score' => $r * 20, 'frequency_score' => $f * 20, 'monetary_score' => $m * 20,
            ]);
            $level = $churn >= 75 ? 'CRITICAL' : ($churn >= 50 ? 'HIGH' : ($churn >= 25 ? 'MEDIUM' : 'LOW'));
            $this->db->insert('churn_predictions', [
                'customer_id' => (int)$c['id'], 'risk_score' => number_format($churn, 2, '.', ''),
                'risk_level' => $level, 'days_since_visit' => $recencyDays,
            ]);
            $count++;
        }
        return $count;
    }

    public function rfmDistribution(): array
    {
        return $this->db->select('SELECT segment, COUNT(*) AS customers FROM rfm_segments GROUP BY segment ORDER BY customers DESC');
    }

    /** Simple moving-average forecast of next month's revenue. */
    public function forecastRevenue(int $months = 3): float
    {
        $rows = $this->db->select(
            "SELECT DATE_FORMAT(issue_date, '%Y-%m') AS p, SUM(total - refunded_amount) AS revenue
             FROM invoices WHERE status <> 'CANCELLED' AND issue_date >= DATE_SUB(CURDATE(), INTERVAL :m MONTH)
             GROUP BY p ORDER BY p",
            ['m' => $months]
        );
        if ($rows === []) {
            return 0.0;
        }
        $sum = array_sum(array_map(static fn ($r) => (float)$r['revenue'], $rows));
        return round($sum / count($rows), 0);
    }

    public function cohortRetention(int $months = 6): array
    {
        return $this->db->select(
            "SELECT DATE_FORMAT(c.created_at, '%Y-%m') AS cohort, COUNT(DISTINCT c.id) AS customers,
                    COUNT(DISTINCT CASE WHEN c.visits_count > 1 THEN c.id END) AS retained
             FROM customers c
             WHERE c.deleted_at IS NULL AND c.created_at >= DATE_SUB(CURDATE(), INTERVAL :m MONTH)
             GROUP BY cohort ORDER BY cohort",
            ['m' => $months]
        );
    }
}
