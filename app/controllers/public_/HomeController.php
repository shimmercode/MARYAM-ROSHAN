<?php
declare(strict_types=1);

namespace App\Controllers\Public_;

use App\Controllers\BaseController;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ServiceRepository;
use App\Repositories\StaffRepository;
use App\Services\NotificationService;
use App\Validators\Validator;

/** Public marketing website. */
final class HomeController extends BaseController
{
    public function index(Request $request): Response
    {
        $services = new ServiceRepository();
        $db       = Database::instance();

        return $this->view('public/home', [
            'title'       => (string)setting('meta_title', 'سالن زیبایی مریم روشن'),
            'description' => (string)setting('meta_description', ''),
            'categories'  => $services->categories(),
            'featured'    => array_values(array_filter($services->publicList(null, 30), static fn ($s) => (int)$s['is_featured'] === 1)),
            'team'        => array_slice((new StaffRepository())->publicTeam(), 0, 4),
            'reviews'     => $db->select(
                "SELECT r.rating, r.comment, r.created_at, c.first_name, c.last_name, sv.name AS service_name
                 FROM reviews r
                 JOIN customers c ON c.id = r.customer_id
                 LEFT JOIN services sv ON sv.id = r.service_id
                 WHERE r.status = 'APPROVED' ORDER BY r.created_at DESC LIMIT 6"
            ),
            'posts'       => $db->select(
                "SELECT slug, title, excerpt, cover, published_at FROM posts
                 WHERE status = 'PUBLISHED' ORDER BY published_at DESC LIMIT 3"
            ),
            'branch'      => $db->selectOne("SELECT name, address, phone, opening_time, closing_time, latitude, longitude FROM branches WHERE status = 'ACTIVE' AND deleted_at IS NULL ORDER BY id LIMIT 1"),
            'stats'       => [
                'customers' => (int)$db->scalar('SELECT COUNT(*) FROM customers WHERE deleted_at IS NULL'),
                'services'  => (int)$db->scalar("SELECT COUNT(*) FROM services WHERE status='ACTIVE' AND deleted_at IS NULL"),
                'staff'     => (int)$db->scalar("SELECT COUNT(*) FROM staff WHERE status='ACTIVE' AND is_public=1 AND deleted_at IS NULL"),
            ],
        ]);
    }

    public function services(Request $request): Response
    {
        $repo     = new ServiceRepository();
        $category = $request->str('category') ?: null;

        return $this->view('public/services', [
            'title'       => 'خدمات سالن زیبایی مریم روشن',
            'description' => 'فهرست کامل خدمات مو، پوست، ناخن، میکاپ و ابرو همراه با قیمت و مدت زمان.',
            'categories'  => $repo->categories(),
            'services'    => $repo->publicList($category),
            'active'      => $category,
        ]);
    }

    public function service(Request $request, string $slug): Response
    {
        $repo    = new ServiceRepository();
        $service = $repo->findBySlug($slug);
        if ($service === null) {
            $this->notFound('خدمت مورد نظر یافت نشد.');
        }

        $related = array_values(array_filter(
            $repo->publicList((string)$service['category_slug'], 8),
            static fn ($s) => (int)$s['id'] !== (int)$service['id']
        ));

        return $this->view('public/service', [
            'title'       => $service['meta_title'] ?: ($service['name'] . ' | سالن زیبایی مریم روشن'),
            'description' => $service['meta_description'] ?: $service['short_description'],
            'service'     => $service,
            'staff'       => $repo->staffFor((int)$service['id']),
            'related'     => array_slice($related, 0, 3),
            'reviews'     => Database::instance()->select(
                "SELECT r.rating, r.comment, r.created_at, c.first_name, c.last_name
                 FROM reviews r JOIN customers c ON c.id = r.customer_id
                 WHERE r.service_id = :s AND r.status = 'APPROVED' ORDER BY r.created_at DESC LIMIT 5",
                ['s' => (int)$service['id']]
            ),
        ]);
    }

    public function team(Request $request): Response
    {
        return $this->view('public/team', [
            'title'       => 'تیم متخصصان | سالن زیبایی مریم روشن',
            'description' => 'با متخصصان مجرب سالن زیبایی مریم روشن آشنا شوید.',
            'team'        => (new StaffRepository())->publicTeam(),
        ]);
    }

    public function gallery(Request $request): Response
    {
        return $this->view('public/gallery', [
            'title'       => 'گالری نمونه‌کارها | سالن زیبایی مریم روشن',
            'description' => 'نمونه‌کارهای رنگ مو، میکاپ، ناخن و خدمات پوست.',
            'items'       => Database::instance()->select(
                "SELECT g.id, g.title, g.image, g.category, sv.name AS service_name
                 FROM gallery_items g LEFT JOIN services sv ON sv.id = g.service_id
                 WHERE g.status = 'ACTIVE' ORDER BY g.sort_order, g.id DESC LIMIT 120"
            ),
        ]);
    }

    public function blog(Request $request): Response
    {
        $page    = $request->page();
        $perPage = 9;
        $db      = Database::instance();

        $total = (int)$db->scalar("SELECT COUNT(*) FROM posts WHERE status = 'PUBLISHED'");
        $posts = $db->select(
            "SELECT slug, title, excerpt, cover, tags, published_at FROM posts
             WHERE status = 'PUBLISHED' ORDER BY published_at DESC LIMIT :l OFFSET :o",
            ['l' => $perPage, 'o' => ($page - 1) * $perPage]
        );

        return $this->view('public/blog', [
            'title'       => 'مجله زیبایی | سالن زیبایی مریم روشن',
            'description' => 'مقالات آموزشی مراقبت از مو، پوست و ناخن.',
            'posts'       => $posts,
            'page'        => $page,
            'lastPage'    => max(1, (int)ceil($total / $perPage)),
        ]);
    }

    public function post(Request $request, string $slug): Response
    {
        $db   = Database::instance();
        $post = $db->selectOne("SELECT * FROM posts WHERE slug = :s AND status = 'PUBLISHED'", ['s' => $slug]);
        if ($post === null) {
            $this->notFound('مطلب مورد نظر یافت نشد.');
        }
        $db->run('UPDATE posts SET views = views + 1 WHERE id = :id', ['id' => (int)$post['id']]);

        return $this->view('public/post', [
            'title'       => $post['meta_title'] ?: $post['title'],
            'description' => $post['meta_description'] ?: $post['excerpt'],
            'post'        => $post,
            'related'     => $db->select(
                "SELECT slug, title, excerpt, cover FROM posts
                 WHERE status = 'PUBLISHED' AND id <> :id ORDER BY published_at DESC LIMIT 3",
                ['id' => (int)$post['id']]
            ),
        ]);
    }

    public function contact(Request $request): Response
    {
        return $this->view('public/contact', [
            'title'       => 'تماس با ما | سالن زیبایی مریم روشن',
            'description' => 'آدرس، تلفن و فرم تماس سالن زیبایی مریم روشن.',
            'branches'    => Database::instance()->select(
                "SELECT name, address, phone, city, opening_time, closing_time, latitude, longitude
                 FROM branches WHERE status = 'ACTIVE' AND deleted_at IS NULL ORDER BY id"
            ),
        ]);
    }

    public function submitContact(Request $request): Response
    {
        $data = Validator::validate($request->all(), [
            'name'    => 'required|string|max:120',
            'mobile'  => 'required|mobile',
            'email'   => 'nullable|email|max:150',
            'subject' => 'nullable|string|max:180',
            'message' => 'required|string|max:2000',
        ], [
            'name' => 'نام', 'mobile' => 'موبایل', 'message' => 'پیام',
        ]);

        Database::instance()->insert('contact_messages', [
            'name'       => $data['name'],
            'mobile'     => $data['mobile'],
            'email'      => $data['email'] ?? null,
            'subject'    => $data['subject'] ?? null,
            'message'    => $data['message'],
            'ip_address' => $request->ip(),
        ]);

        NotificationService::notifyByPermission(
            'settings.manage',
            'پیام جدید از فرم تماس',
            $data['name'] . ' پیامی ارسال کرده است.',
            'INFO',
            '/admin/settings'
        );

        if ($request->wantsJson()) {
            return $this->json(['sent' => true]);
        }
        return $this->back('success', 'پیام شما ثبت شد. به‌زودی با شما تماس می‌گیریم.');
    }
}
