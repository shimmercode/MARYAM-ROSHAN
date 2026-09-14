<?php
declare(strict_types=1);

namespace App\Controllers\Customer;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Core\Request;
use App\Core\Response;
use App\Helpers\Format;
use App\Helpers\Jalali;
use App\Repositories\CustomerRepository;
use App\Services\AppointmentService;
use App\Services\AuditService;
use App\Services\LoyaltyService;
use App\Validators\Validator;

/**
 * Mobile-first portal for customers. Every read and write is scoped to the
 * customer record linked to the signed-in user account.
 */
final class PortalController extends BaseController
{
    private CustomerRepository $customers;

    public function __construct()
    {
        $this->customers = new CustomerRepository();
    }

    public function index(Request $request): Response
    {
        $me      = $this->me();
        $db      = Database::instance();
        $loyalty = new LoyaltyService();

        return $this->view('customer/dashboard', [
            'title'    => 'پنل من',
            'customer' => $me,
            'tier'     => $loyalty->currentTier((int)$me['id']),
            'points'   => $loyalty->balance((int)$me['id']),
            'wallet'   => (float)($db->scalar('SELECT balance FROM wallet_accounts WHERE customer_id = :c', ['c' => $me['id']]) ?? 0),
            'next'     => $db->selectOne(
                "SELECT a.id, a.code, a.appointment_date, a.start_time, a.status,
                        CONCAT(st.first_name,' ',st.last_name) AS staff_name, b.name AS branch_name, b.address,
                        (SELECT GROUP_CONCAT(s.name SEPARATOR '، ') FROM appointment_items ai
                         JOIN services s ON s.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
                 FROM appointments a
                 JOIN staff st ON st.id = a.staff_id
                 JOIN branches b ON b.id = a.branch_id
                 WHERE a.customer_id = :c AND a.status IN ('PENDING','CONFIRMED')
                   AND CONCAT(a.appointment_date,' ',a.start_time) >= NOW()
                 ORDER BY a.appointment_date, a.start_time LIMIT 1",
                ['c' => $me['id']]
            ),
            'recent'   => $this->customers->appointments((int)$me['id'], 5),
            'unpaid'   => $db->select(
                "SELECT id, invoice_number, issue_date, total, paid_amount, (total - paid_amount) AS due
                 FROM invoices WHERE customer_id = :c AND status IN ('ISSUED','PARTIAL') AND total > paid_amount
                 ORDER BY issue_date DESC LIMIT 5",
                ['c' => $me['id']]
            ),
            'statuses' => AppointmentService::STATUSES,
        ]);
    }

    public function appointments(Request $request): Response
    {
        $me    = $this->me();
        $scope = $request->str('scope', 'upcoming');
        $db    = Database::instance();

        $condition = $scope === 'past'
            ? "AND (a.appointment_date < CURDATE() OR a.status IN ('COMPLETED','CANCELLED','NO_SHOW'))"
            : "AND a.appointment_date >= CURDATE() AND a.status IN ('PENDING','CONFIRMED','IN_PROGRESS')";

        $rows = $db->select(
            "SELECT a.id, a.code, a.appointment_date, a.start_time, a.end_time, a.status, a.total_price,
                    CONCAT(st.first_name,' ',st.last_name) AS staff_name,
                    b.name AS branch_name, b.address, b.phone,
                    (SELECT GROUP_CONCAT(s.name SEPARATOR '، ') FROM appointment_items ai
                     JOIN services s ON s.id = ai.service_id WHERE ai.appointment_id = a.id) AS services,
                    (SELECT COUNT(*) FROM reviews r WHERE r.appointment_id = a.id) AS reviewed
             FROM appointments a
             JOIN staff st ON st.id = a.staff_id
             JOIN branches b ON b.id = a.branch_id
             WHERE a.customer_id = :c {$condition}
             ORDER BY a.appointment_date " . ($scope === 'past' ? 'DESC' : 'ASC') . ", a.start_time LIMIT 60",
            ['c' => $me['id']]
        );
        foreach ($rows as &$r) {
            $r['date_fa']    = Jalali::format($r['appointment_date'], 'l j F Y');
            $r['cancelable'] = in_array($r['status'], ['PENDING', 'CONFIRMED'], true)
                && strtotime($r['appointment_date'] . ' ' . $r['start_time']) - time() > 3600 * (int)setting('cancel_hours', 6);
        }
        unset($r);

        return $this->view('customer/appointments', [
            'title'        => 'نوبت‌های من',
            'customer'     => $me,
            'appointments' => $rows,
            'scope'        => $scope,
            'statuses'     => AppointmentService::STATUSES,
        ]);
    }

    public function cancel(Request $request, string $id): Response
    {
        $me            = $this->me();
        $appointmentId = (int)$id;

        $owner = (int)Database::instance()->scalar('SELECT customer_id FROM appointments WHERE id = :id', ['id' => $appointmentId]);
        if ($owner !== (int)$me['id']) {
            return $this->fail('FORBIDDEN', 'این نوبت متعلق به شما نیست.', 403);
        }

        try {
            // enforcePolicy = true → respects the configured cancellation window.
            (new AppointmentService())->cancel(
                $appointmentId,
                $request->str('reason') ?: 'لغو توسط مشتری',
                $this->userId(),
                true
            );
        } catch (BusinessException $e) {
            if ($request->wantsJson()) {
                return $this->fail($e->errorCode(), $e->getMessage(), 422);
            }
            return $this->back('error', $e->getMessage());
        }

        AuditService::log('appointment_cancelled_by_customer', 'appointments', $appointmentId);

        if ($request->wantsJson()) {
            return $this->json(['cancelled' => true]);
        }
        return $this->back('success', 'نوبت شما لغو شد.');
    }

    public function invoices(Request $request): Response
    {
        $me = $this->me();

        return $this->view('customer/invoices', [
            'title'    => 'فاکتورهای من',
            'customer' => $me,
            'invoices' => $this->customers->invoices((int)$me['id'], 50),
            'totals'   => Database::instance()->selectOne(
                "SELECT COALESCE(SUM(total),0) AS total, COALESCE(SUM(paid_amount),0) AS paid,
                        COALESCE(SUM(total - paid_amount),0) AS due
                 FROM invoices WHERE customer_id = :c AND status <> 'CANCELLED'",
                ['c' => $me['id']]
            ) ?? ['total' => 0, 'paid' => 0, 'due' => 0],
        ]);
    }

    public function invoice(Request $request, string $id): Response
    {
        $me = $this->me();
        $db = Database::instance();

        $invoice = $db->selectOne(
            "SELECT i.*, b.name AS branch_name, b.address AS branch_address, b.phone AS branch_phone
             FROM invoices i JOIN branches b ON b.id = i.branch_id
             WHERE i.id = :id AND i.customer_id = :c",
            ['id' => (int)$id, 'c' => $me['id']]
        );
        if ($invoice === null) {
            $this->notFound('فاکتور یافت نشد.');
        }

        return $this->view('customer/invoice', [
            'title'    => 'فاکتور ' . $invoice['invoice_number'],
            'customer' => $me,
            'invoice'  => $invoice,
            'items'    => $db->select(
                'SELECT title AS description, quantity, unit_price, discount AS discount_amount, total
                 FROM invoice_items WHERE invoice_id = :i ORDER BY id',
                ['i' => (int)$id]
            ),
            'payments' => $db->select(
                'SELECT amount, method_slug AS method, paid_at, reference FROM payments
                 WHERE invoice_id = :i ORDER BY id',
                ['i' => (int)$id]
            ),
        ]);
    }

    public function loyalty(Request $request): Response
    {
        $me      = $this->me();
        $loyalty = new LoyaltyService();
        $balance = $loyalty->balance((int)$me['id']);
        $tiers   = $loyalty->tiers();
        $current = $loyalty->currentTier((int)$me['id']);

        $next = null;
        foreach ($tiers as $tier) {
            if ((int)$tier['min_points'] > $balance) {
                $next = $tier;
                break;
            }
        }

        return $this->view('customer/loyalty', [
            'title'      => 'باشگاه مشتریان',
            'customer'   => $me,
            'balance'    => $balance,
            'pointValue' => $loyalty->pointValue(),
            'tiers'      => $tiers,
            'tier'       => $current,
            'next'       => $next,
            'toNext'     => $next !== null ? max(0, (int)$next['min_points'] - $balance) : 0,
            'history'    => $loyalty->history((int)$me['id'], 40),
            'referral'   => $loyalty->createReferralCode((int)$me['id']),
        ]);
    }

    public function wallet(Request $request): Response
    {
        $me = $this->me();
        $db = Database::instance();

        return $this->view('customer/wallet', [
            'title'    => 'کیف پول',
            'customer' => $me,
            'balance'  => (float)($db->scalar('SELECT balance FROM wallet_accounts WHERE customer_id = :c', ['c' => $me['id']]) ?? 0),
            'history'  => $this->customers->walletHistory((int)$me['id'], 50),
        ]);
    }

    public function profile(Request $request): Response
    {
        $me = $this->me();

        return $this->view('customer/profile', [
            'title'    => 'پروفایل من',
            'customer' => $me,
            'birthFa'  => $me['birth_date'] ? Jalali::format($me['birth_date'], 'Y/m/d') : '',
        ]);
    }

    public function updateProfile(Request $request): Response
    {
        $me   = $this->me();
        $data = Validator::validate($request->all(), [
            'first_name'  => 'required|string|max:80',
            'last_name'   => 'required|string|max:80',
            'email'       => 'nullable|email|max:150',
            'birth_date'  => 'nullable|string|max:12',
            'address'     => 'nullable|string|max:255',
            'marketing_opt_in' => 'nullable|bool',
        ], [
            'first_name' => 'نام', 'last_name' => 'نام خانوادگی',
            'email' => 'ایمیل', 'birth_date' => 'تاریخ تولد',
        ]);

        $birth = null;
        if (!empty($data['birth_date'])) {
            $birth = Jalali::parse((string)$data['birth_date']);
            if ($birth === null) {
                return $this->back('error', 'تاریخ تولد باید به شکل ۱۳۷۰/۰۵/۱۲ وارد شود.');
            }
        }

        Database::instance()->update('customers', [
            'first_name'       => $data['first_name'],
            'last_name'        => $data['last_name'],
            'email'            => $data['email'] ?: null,
            'birth_date'       => $birth,
            'address'          => $data['address'] ?? null,
            'marketing_opt_in' => $request->bool('marketing_opt_in') ? 1 : 0,
        ], 'id = :id', ['id' => $me['id']]);

        AuditService::log('customer_profile_updated', 'customers', (int)$me['id']);
        return $this->back('success', 'اطلاعات شما ذخیره شد.');
    }

    public function reviews(Request $request): Response
    {
        $me = $this->me();

        return $this->view('customer/reviews', [
            'title'      => 'نظرات من',
            'customer'   => $me,
            'reviews'    => $this->customers->reviews((int)$me['id']),
            'reviewable' => Database::instance()->select(
                "SELECT a.id, a.code, a.appointment_date, a.staff_id,
                        CONCAT(st.first_name,' ',st.last_name) AS staff_name,
                        (SELECT MIN(service_id) FROM appointment_items WHERE appointment_id = a.id) AS service_id,
                        (SELECT GROUP_CONCAT(s.name SEPARATOR '، ') FROM appointment_items ai
                         JOIN services s ON s.id = ai.service_id WHERE ai.appointment_id = a.id) AS services
                 FROM appointments a JOIN staff st ON st.id = a.staff_id
                 WHERE a.customer_id = :c AND a.status = 'COMPLETED'
                   AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.appointment_id = a.id)
                 ORDER BY a.appointment_date DESC LIMIT 20",
                ['c' => $me['id']]
            ),
        ]);
    }

    public function storeReview(Request $request): Response
    {
        $me   = $this->me();
        $data = Validator::validate($request->all(), [
            'appointment_id' => 'required|int',
            'rating'         => 'required|int|min:1|max:5',
            'title'          => 'nullable|string|max:120',
            'comment'        => 'nullable|string|max:1000',
        ], ['rating' => 'امتیاز', 'comment' => 'متن نظر']);

        $db          = Database::instance();
        $appointment = $db->selectOne(
            'SELECT id, staff_id, branch_id, status FROM appointments WHERE id = :id AND customer_id = :c',
            ['id' => (int)$data['appointment_id'], 'c' => $me['id']]
        );
        if ($appointment === null) {
            return $this->back('error', 'نوبت یافت نشد.');
        }
        if ($appointment['status'] !== 'COMPLETED') {
            return $this->back('error', 'فقط برای نوبت‌های انجام‌شده می‌توانید نظر ثبت کنید.');
        }
        $already = (int)$db->scalar('SELECT COUNT(*) FROM reviews WHERE appointment_id = :a', ['a' => $appointment['id']]);
        if ($already > 0) {
            return $this->back('error', 'برای این نوبت قبلاً نظر ثبت کرده‌اید.');
        }

        $serviceId = $db->scalar('SELECT MIN(service_id) FROM appointment_items WHERE appointment_id = :a', ['a' => $appointment['id']]);

        $db->insert('reviews', [
            'customer_id'    => (int)$me['id'],
            'staff_id'       => (int)$appointment['staff_id'],
            'service_id'     => $serviceId ? (int)$serviceId : null,
            'branch_id'      => (int)$appointment['branch_id'],
            'appointment_id' => (int)$appointment['id'],
            'rating'         => (int)$data['rating'],
            'title'          => $data['title'] ?: null,
            'comment'        => $data['comment'] ?: null,
            // Reviews are moderated before appearing on the public site.
            'status'         => 'PENDING',
            'is_public'      => 1,
        ]);

        return $this->back('success', 'نظر شما ثبت شد و پس از بررسی منتشر می‌شود. سپاسگزاریم!');
    }

    /* ====================================================== internals == */

    private function me(): array
    {
        $customer = Database::instance()->selectOne(
            'SELECT c.*, t.name AS tier_name, t.color AS tier_color, t.discount_percent
             FROM customers c LEFT JOIN loyalty_tiers t ON t.id = c.loyalty_tier_id
             WHERE c.user_id = :u AND c.deleted_at IS NULL LIMIT 1',
            ['u' => (int)$this->userId()]
        );
        if ($customer === null) {
            $this->notFound('پرونده مشتری برای حساب شما یافت نشد.');
        }
        $customer['mobile_fa'] = Format::mobile((string)$customer['mobile']);

        return $customer;
    }
}
