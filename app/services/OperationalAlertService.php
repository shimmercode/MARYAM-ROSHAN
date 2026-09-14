<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;

final class OperationalAlertService
{
    public function __construct(private ?Database $db = null)
    { $this->db ??= Database::instance(); }

    public function sync(?int $branchId = null): array
    {
        $live = new LiveStatusService($this->db);
        $alerts = $live->alerts($branchId);
        foreach ($alerts as $alert) {
            $key = $alert['type'] . ':' . ($alert['staff_id'] ?? 'global');
            $title = $alert['type'] === 'EMPTY_SEAT' ? 'هشدار جایگاه خالی' : 'نیازمند بررسی پرسنل';
            $body = ($alert['staff_name'] ?? '') . ' — ' . $alert['message'];
            $this->db->run("INSERT INTO operational_alerts (alert_key,type,severity,title,body,staff_id,last_seen_at,status)
                VALUES (:k,:t,'WARNING',:title,:body,:staff,NOW(),'OPEN')
                ON DUPLICATE KEY UPDATE body=VALUES(body),last_seen_at=NOW(),status=IF(status='RESOLVED','OPEN',status),resolved_at=NULL",
                ['k'=>$key,'t'=>$alert['type'],'title'=>$title,'body'=>$body,'staff'=>$alert['staff_id'] ?? null]);
        }
        if ($alerts === []) {
            $this->db->execute("UPDATE operational_alerts SET status='RESOLVED', resolved_at=NOW() WHERE status='OPEN' AND last_seen_at < DATE_SUB(NOW(), INTERVAL 30 SECOND)");
        }
        return $this->db->select("SELECT id,type,severity,title,body,staff_id,status,first_seen_at,last_seen_at FROM operational_alerts WHERE status IN ('OPEN','ACKNOWLEDGED') ORDER BY last_seen_at DESC LIMIT 30");
    }
}
