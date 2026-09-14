<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\Exceptions\BusinessException;
use App\Core\Logger;
use App\Core\Request;
use App\Core\Session;
use App\Helpers\Format;
use App\Repositories\UserRepository;

final class AuthService
{
    private static ?array $userCache = null;
    private static ?array $permCache = null;

    /* ------------------------------------------------------------------ */
    /* State                                                               */
    /* ------------------------------------------------------------------ */

    public static function check(): bool
    {
        return Session::get('user_id') !== null;
    }

    public static function id(): ?int
    {
        $id = Session::get('user_id');
        return $id === null ? null : (int)$id;
    }

    public static function currentUser(): ?array
    {
        if (!self::check()) {
            return null;
        }
        if (self::$userCache !== null && (int)self::$userCache['id'] === self::id()) {
            return self::$userCache;
        }
        $repo = new UserRepository();
        $user = $repo->find((int)self::id());
        if ($user === null || $user['status'] !== 'ACTIVE') {
            self::logout();
            return null;
        }
        $user['roles']       = array_column($repo->rolesOf((int)$user['id']), 'slug');
        $user['role_names']  = array_column($repo->rolesOf((int)$user['id']), 'name');
        $user['full_name']   = trim($user['first_name'] . ' ' . $user['last_name']);
        $user['staff']       = self::staffRecord((int)$user['id']);
        $user['customer_id'] = self::customerId((int)$user['id']);
        self::$userCache     = $user;
        return $user;
    }

    private static function staffRecord(int $userId): ?array
    {
        return Database::instance()->selectOne(
            'SELECT id, code, first_name, last_name, branch_id, job_title, commission_percent
             FROM staff WHERE user_id = :u AND deleted_at IS NULL LIMIT 1',
            ['u' => $userId]
        );
    }

    private static function customerId(int $userId): ?int
    {
        $id = Database::instance()->scalar(
            'SELECT id FROM customers WHERE user_id = :u AND deleted_at IS NULL LIMIT 1',
            ['u' => $userId]
        );
        return $id ? (int)$id : null;
    }

    /* ------------------------------------------------------------------ */
    /* RBAC                                                                */
    /* ------------------------------------------------------------------ */

    public static function permissions(): array
    {
        if (!self::check()) {
            return [];
        }
        if (self::$permCache !== null) {
            return self::$permCache;
        }
        $cached = Session::get('_permissions');
        if (is_array($cached)) {
            return self::$permCache = $cached;
        }
        $perms = (new UserRepository())->permissionsOf((int)self::id());
        Session::set('_permissions', $perms);
        return self::$permCache = $perms;
    }

    public static function can(string $permission): bool
    {
        if (!self::check()) {
            return false;
        }
        if (self::hasRole('SUPER_ADMIN')) {
            return true;
        }
        $perms = self::permissions();
        if (in_array($permission, $perms, true)) {
            return true;
        }
        // Wildcard support: "customers.*"
        $module = explode('.', $permission)[0] ?? '';
        return in_array($module . '.*', $perms, true);
    }

    public static function hasRole(string $role): bool
    {
        $user = self::currentUser();
        return $user !== null && in_array($role, $user['roles'] ?? [], true);
    }

    public static function hasAnyRole(array $roles): bool
    {
        foreach ($roles as $r) {
            if (self::hasRole($r)) {
                return true;
            }
        }
        return false;
    }

    public static function homeRoute(): string
    {
        $user = self::currentUser();
        $map  = (array)Config::get('auth.home_route', []);
        foreach (['SUPER_ADMIN', 'ADMIN', 'BRANCH_MANAGER', 'RECEPTION', 'SPECIALIST', 'CUSTOMER'] as $role) {
            if (in_array($role, $user['roles'] ?? [], true)) {
                return (string)($map[$role] ?? '/');
            }
        }
        return '/';
    }

    /* ------------------------------------------------------------------ */
    /* Login / logout                                                      */
    /* ------------------------------------------------------------------ */

    public static function attempt(string $identifier, string $password, bool $remember, Request $request): array
    {
        $identifier = trim($identifier);
        if (Format::isValidMobile($identifier)) {
            $identifier = Format::mobile($identifier);
        }

        self::assertNotThrottled($identifier, $request->ip());

        $repo = new UserRepository();
        $user = $repo->findByIdentifier($identifier);

        if ($user === null || !password_verify($password, (string)$user['password_hash'])) {
            self::recordAttempt($identifier, $request, false);
            Logger::security('Failed login attempt', ['identifier' => $identifier, 'ip' => $request->ip()]);
            throw new BusinessException('نام کاربری یا رمز عبور اشتباه است.', 'INVALID_CREDENTIALS', 401);
        }

        if ($user['status'] !== 'ACTIVE') {
            self::recordAttempt($identifier, $request, false);
            throw new BusinessException('حساب کاربری شما غیرفعال است. با مدیریت تماس بگیرید.', 'ACCOUNT_INACTIVE', 403);
        }

        // Re-hash if the cost parameters changed.
        if (password_needs_rehash((string)$user['password_hash'], (int)Config::get('auth.password.algo'), (array)Config::get('auth.password.options'))) {
            $repo->update((int)$user['id'], ['password_hash' => self::hash($password)]);
        }

        self::recordAttempt($identifier, $request, true);
        self::establishSession((int)$user['id'], $request);

        if ($remember) {
            self::issueRememberToken((int)$user['id'], $request);
        }

        AuditService::log('login', 'users', (int)$user['id'], null, ['mobile' => $user['mobile']], (int)$user['id']);
        Logger::security('Successful login', ['user_id' => $user['id'], 'ip' => $request->ip()]);

        return self::currentUser() ?? [];
    }

    public static function establishSession(int $userId, Request $request): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::forget('_permissions');
        self::$userCache = null;
        self::$permCache = null;
        Csrf::rotate();

        (new UserRepository())->updateLastLogin($userId, $request->ip());

        try {
            Database::instance()->insert('sessions', [
                'id'            => session_id() ?: bin2hex(random_bytes(16)),
                'user_id'       => $userId,
                'ip_address'    => $request->ip(),
                'user_agent'    => $request->userAgent(),
                'last_activity' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable) {
            // Duplicate session id or storage issue must not block login.
        }
    }

    public static function logout(): void
    {
        $userId = self::id();
        if ($userId !== null) {
            AuditService::log('logout', 'users', $userId, null, null, $userId);
            try {
                Database::instance()->execute('UPDATE users SET remember_token = NULL, remember_expires_at = NULL WHERE id = :u', ['u' => $userId]);
                Database::instance()->delete('sessions', 'id = :sid', ['sid' => session_id() ?: '']);
            } catch (\Throwable) {
            }
        }
        self::$userCache = null;
        self::$permCache = null;
        Session::destroy();
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie((string)Config::get('auth.remember.cookie', 'mr_remember'), '', time() - 3600, '/');
        }
    }

    /* ------------------------------------------------------------------ */
    /* Remember me                                                         */
    /* ------------------------------------------------------------------ */

    public static function issueRememberToken(int $userId, Request $request): void
    {
        $token = bin2hex(random_bytes(32));
        $exp   = time() + (int)Config::get('auth.remember.lifetime', 2592000);
        Database::instance()->execute(
            'UPDATE users SET remember_token = :t, remember_expires_at = :e WHERE id = :u',
            ['t' => hash('sha256', $token), 'e' => date('Y-m-d H:i:s', $exp), 'u' => $userId]
        );
        if (PHP_SAPI !== 'cli' && !headers_sent()) {
            setcookie((string)Config::get('auth.remember.cookie', 'mr_remember'), $userId . '|' . $token, [
                'expires'  => $exp,
                'path'     => '/',
                'secure'   => $request->isSecure(),
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }
    }

    public static function loginFromRememberCookie(Request $request): bool
    {
        $raw = (string)$request->cookie((string)Config::get('auth.remember.cookie', 'mr_remember'), '');
        if ($raw === '' || !str_contains($raw, '|')) {
            return false;
        }
        [$uid, $token] = explode('|', $raw, 2);
        $row = Database::instance()->selectOne(
            'SELECT id, remember_token, remember_expires_at, status FROM users WHERE id = :u AND deleted_at IS NULL',
            ['u' => (int)$uid]
        );
        if (!$row || !$row['remember_token'] || $row['status'] !== 'ACTIVE') {
            return false;
        }
        if (strtotime((string)$row['remember_expires_at']) < time()) {
            return false;
        }
        if (!hash_equals((string)$row['remember_token'], hash('sha256', $token))) {
            Logger::security('Invalid remember token', ['user_id' => $uid, 'ip' => $request->ip()]);
            return false;
        }
        self::establishSession((int)$row['id'], $request);
        self::issueRememberToken((int)$row['id'], $request); // rotate
        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Throttling                                                          */
    /* ------------------------------------------------------------------ */

    private static function assertNotThrottled(string $identifier, string $ip): void
    {
        $max   = (int)Config::get('auth.throttle.max_attempts', 5);
        $decay = (int)Config::get('auth.throttle.decay_minutes', 15);
        $since = date('Y-m-d H:i:s', time() - $decay * 60);

        $count = (int)Database::instance()->scalar(
            'SELECT COUNT(*) FROM login_attempts
             WHERE successful = 0 AND attempted_at >= :since AND (identifier = :id OR ip_address = :ip)',
            ['since' => $since, 'id' => $identifier, 'ip' => $ip]
        );
        if ($count >= $max) {
            Logger::security('Login throttled', ['identifier' => $identifier, 'ip' => $ip, 'attempts' => $count]);
            throw new BusinessException(
                'به دلیل تلاش‌های ناموفق متعدد، ورود موقتاً مسدود شده است. لطفاً ' . $decay . ' دقیقه دیگر تلاش کنید.',
                'TOO_MANY_ATTEMPTS',
                429
            );
        }
    }

    private static function recordAttempt(string $identifier, Request $request, bool $ok): void
    {
        try {
            Database::instance()->insert('login_attempts', [
                'identifier'  => mb_substr($identifier, 0, 150),
                'ip_address'  => $request->ip(),
                'user_agent'  => $request->userAgent(),
                'successful'  => $ok ? 1 : 0,
                'attempted_at' => date('Y-m-d H:i:s'),
            ]);
            if ($ok) {
                Database::instance()->delete(
                    'login_attempts',
                    'identifier = :id AND successful = 0',
                    ['id' => $identifier]
                );
            }
        } catch (\Throwable $e) {
            Logger::error('Could not record login attempt', ['message' => $e->getMessage()]);
        }
    }

    /* ------------------------------------------------------------------ */
    /* Passwords                                                           */
    /* ------------------------------------------------------------------ */

    public static function hash(string $password): string
    {
        return password_hash($password, (int)Config::get('auth.password.algo', PASSWORD_BCRYPT), (array)Config::get('auth.password.options', []));
    }

    public static function changePassword(int $userId, string $current, string $new): void
    {
        $row = Database::instance()->selectOne('SELECT password_hash FROM users WHERE id = :u', ['u' => $userId]);
        if (!$row || !password_verify($current, (string)$row['password_hash'])) {
            throw new BusinessException('رمز عبور فعلی صحیح نیست.', 'INVALID_PASSWORD', 422);
        }
        self::forceSetPassword($userId, $new);
    }

    public static function forceSetPassword(int $userId, string $new): void
    {
        $min = (int)Config::get('auth.password.min_length', 8);
        if (mb_strlen($new) < $min) {
            throw new BusinessException('رمز عبور باید حداقل ' . $min . ' کاراکتر باشد.', 'WEAK_PASSWORD', 422);
        }
        Database::instance()->update('users', [
            'password_hash'        => self::hash($new),
            'password_changed_at'  => date('Y-m-d H:i:s'),
            'must_change_password' => 0,
            'remember_token'       => null,
        ], 'id = :u', ['u' => $userId]);
        AuditService::log('password_changed', 'users', $userId);
        Logger::security('Password changed', ['user_id' => $userId]);
    }

    /* ------------------------------------------------------------------ */
    /* Password reset                                                      */
    /* ------------------------------------------------------------------ */

    public static function createPasswordReset(string $identifier, Request $request): ?string
    {
        $user = (new UserRepository())->findByIdentifier(Format::isValidMobile($identifier) ? Format::mobile($identifier) : $identifier);
        if ($user === null) {
            return null; // Do not disclose existence.
        }
        $token = bin2hex(random_bytes(32));
        Database::instance()->insert('password_resets', [
            'user_id'    => (int)$user['id'],
            'token_hash' => hash('sha256', $token),
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'ip_address' => $request->ip(),
        ]);
        Logger::security('Password reset requested', ['user_id' => $user['id']]);
        return $token;
    }

    public static function resetPassword(string $token, string $newPassword): void
    {
        $row = Database::instance()->selectOne(
            'SELECT id, user_id FROM password_resets
             WHERE token_hash = :t AND used_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            ['t' => hash('sha256', $token)]
        );
        if ($row === null) {
            throw new BusinessException('لینک بازیابی نامعتبر یا منقضی شده است.', 'INVALID_RESET_TOKEN', 422);
        }
        Database::instance()->transaction(function () use ($row, $newPassword): void {
            self::forceSetPassword((int)$row['user_id'], $newPassword);
            Database::instance()->update('password_resets', ['used_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int)$row['id']]);
        });
    }

    /* ------------------------------------------------------------------ */
    /* Two-factor (OTP) — architecture ready                               */
    /* ------------------------------------------------------------------ */

    public static function issueOtp(int $userId, string $channel = 'SMS'): string
    {
        $len  = (int)Config::get('auth.two_factor.otp_length', 6);
        $code = str_pad((string)random_int(0, (int)str_repeat('9', $len)), $len, '0', STR_PAD_LEFT);
        Database::instance()->insert('two_factor_auth', [
            'user_id'    => $userId,
            'channel'    => $channel,
            'code_hash'  => password_hash($code, PASSWORD_BCRYPT),
            'expires_at' => date('Y-m-d H:i:s', time() + (int)Config::get('auth.two_factor.otp_ttl', 300)),
        ]);
        return $code;
    }

    public static function verifyOtp(int $userId, string $code): bool
    {
        $row = Database::instance()->selectOne(
            'SELECT id, code_hash, attempts FROM two_factor_auth
             WHERE user_id = :u AND consumed_at IS NULL AND expires_at > NOW() ORDER BY id DESC LIMIT 1',
            ['u' => $userId]
        );
        if ($row === null || (int)$row['attempts'] >= 5) {
            return false;
        }
        if (!password_verify($code, (string)$row['code_hash'])) {
            Database::instance()->execute('UPDATE two_factor_auth SET attempts = attempts + 1 WHERE id = :id', ['id' => (int)$row['id']]);
            return false;
        }
        Database::instance()->update('two_factor_auth', ['consumed_at' => date('Y-m-d H:i:s')], 'id = :id', ['id' => (int)$row['id']]);
        return true;
    }

    /** Test/CLI helper. */
    public static function loginAs(int $userId): void
    {
        Session::set('user_id', $userId);
        Session::forget('_permissions');
        self::$userCache = null;
        self::$permCache = null;
    }

    public static function flushCache(): void
    {
        self::$userCache = null;
        self::$permCache = null;
        Session::forget('_permissions');
    }
}
