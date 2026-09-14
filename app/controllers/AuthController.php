<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Helpers\Format;
use App\Repositories\AppointmentRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\StaffRepository;
use App\Services\AuthService;
use App\Services\NotificationService;
use App\Validators\Validator;

final class AuthController extends BaseController
{
    public function showLogin(Request $request): Response
    {
        $stats = [
            'customers'    => (new CustomerRepository())->count(),
            'staff'        => (new StaffRepository())->count(),
            'appointments' => (new AppointmentRepository())->count(),
        ];

        return $this->view('auth/login', ['title' => 'ورود به سامانه', 'stats' => $stats, 'split' => true]);
    }

    public function login(Request $request): Response
    {
        $data = Validator::validate($request->only(['identifier', 'password']), [
            'identifier' => 'required|string|max:150',
            'password'   => 'required|string|min:4|max:200',
        ], ['identifier' => 'موبایل یا ایمیل', 'password' => 'رمز عبور']);

        AuthService::attempt(
            (string)$data['identifier'],
            (string)$data['password'],
            $request->bool('remember'),
            $request
        );

        $intended = Session::get('_intended_url');
        Session::forget('_intended_url');
        $target = is_string($intended) && $intended !== '' && str_starts_with($intended, '/')
            ? $intended
            : AuthService::homeRoute();

        if ($request->wantsJson()) {
            return $this->json(['redirect' => url($target)]);
        }
        return $this->redirect($target, 'success', 'خوش آمدید!');
    }

    public function logout(Request $request): Response
    {
        AuthService::logout();
        if ($request->wantsJson()) {
            return $this->json(['redirect' => url('/login')]);
        }
        return $this->redirect('/login', 'success', 'با موفقیت خارج شدید.');
    }

    public function showForgot(Request $request): Response
    {
        return $this->view('auth/forgot', ['title' => 'بازیابی رمز عبور']);
    }

    public function sendReset(Request $request): Response
    {
        $data = Validator::validate($request->only(['identifier']), [
            'identifier' => 'required|string|max:150',
        ], ['identifier' => 'موبایل یا ایمیل']);

        $token = AuthService::createPasswordReset((string)$data['identifier'], $request);
        if ($token !== null) {
            $identifier = (string)$data['identifier'];
            $link = url('/reset-password?token=' . $token);
            if (Format::isValidMobile($identifier)) {
                NotificationService::sendSms(
                    Format::mobile($identifier),
                    'سالن مریم روشن: لینک بازیابی رمز عبور شما: ' . $link
                );
            } else {
                NotificationService::sendEmail($identifier, 'بازیابی رمز عبور', 'برای تغییر رمز عبور روی لینک زیر کلیک کنید:<br>' . $link);
            }
        }

        // Identical response regardless of existence (no user enumeration).
        return $this->back('success', 'در صورت وجود حساب، لینک بازیابی برای شما ارسال شد.');
    }

    public function showReset(Request $request, string $token = ''): Response
    {
        return $this->view('auth/reset', [
            'title' => 'تعیین رمز عبور جدید',
            'token' => $token !== '' ? $token : (string)$request->query('token', ''),
        ]);
    }

    public function resetPassword(Request $request): Response
    {
        $data = Validator::validate($request->only(['token', 'password', 'password_confirmation']), [
            'token'    => 'required|string',
            'password' => 'required|string|min:8|max:200|confirmed',
        ], ['token' => 'توکن', 'password' => 'رمز عبور']);

        AuthService::resetPassword((string)$data['token'], (string)$data['password']);
        return $this->redirect('/login', 'success', 'رمز عبور با موفقیت تغییر کرد. اکنون وارد شوید.');
    }

    public function showProfile(Request $request): Response
    {
        return $this->view('auth/profile', ['title' => 'پروفایل من']);
    }

    public function updatePassword(Request $request): Response
    {
        $data = Validator::validate($request->only(['current_password', 'password', 'password_confirmation']), [
            'current_password' => 'required|string',
            'password'         => 'required|string|min:8|max:200|confirmed',
        ], ['current_password' => 'رمز عبور فعلی', 'password' => 'رمز عبور جدید']);

        AuthService::changePassword((int)$this->userId(), (string)$data['current_password'], (string)$data['password']);
        return $this->back('success', 'رمز عبور با موفقیت تغییر کرد.');
    }
}
