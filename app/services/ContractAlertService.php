<?php
declare(strict_types=1);
namespace App\Services;
use App\Core\Database;
final class ContractAlertService
{
    public function __construct(private ?Database $db = null) { $this->db ??= Database::instance(); }
    public function process(int $days = 10): int
    {
        $rows = $this->db->select("SELECT c.id,c.staff_id,c.ends_on,u.id AS user_id,CONCAT(s.first_name,' ',s.last_name) AS name
            FROM staff_contracts c JOIN staff s ON s.id=c.staff_id LEFT JOIN users u ON u.id=s.user_id
            WHERE c.status='ACTIVE' AND c.ends_on IS NOT NULL AND c.ends_on BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL :days DAY)", ['days'=>$days]);
        foreach ($rows as $r) {
            $key = 'CONTRACT_EXPIRY:' . $r['id'];
            $this->db->run("INSERT INTO operational_alerts (alert_key,type,severity,title,body,staff_id,status,last_seen_at)
                VALUES (:k,'CONTRACT_EXPIRY','WARNING','انقضای قرارداد',:body,:staff,'OPEN',NOW())
                ON DUPLICATE KEY UPDATE body=VALUES(body),last_seen_at=NOW(),status=IF(status='RESOLVED','OPEN',status)",
                ['k'=>$key,'body'=>$r['name'].' تا تاریخ '.$r['ends_on'].' قرارداد فعال دارد.','staff'=>$r['staff_id']]);
            if ($r['user_id']) NotificationService::notifyUser((int)$r['user_id'], 'هشدار انقضای قرارداد', 'قرارداد شما تا '.$r['ends_on'].' معتبر است.', 'WARNING', '/staff');
        }
        return count($rows);
    }
}
