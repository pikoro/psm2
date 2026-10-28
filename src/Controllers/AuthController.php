<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Auth;
use App\Response;
use App\ResponseHandler;
use App\Session;
use App\CSRF;
use App\Validator;
use App\Models\User;

final class AuthController
{
    public function showLogin(): void
    {
        if (Auth::isLoggedIn()) {
            Response::redirect('/desktop');
        }
        (new ResponseHandler('auth/login.html.twig'))->render();
    }

    public function login(): void
    {
        if (Auth::isLoggedIn()) {
            Session::flash('info', 'You are already logged in.');
            Response::redirect('/desktop');
        }

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/login');
        }

        $validator = Validator::make($_POST)
            ->required('username')
            ->required('password')
            ->max('username', 50);

        if (!$validator->validate()) {
            Session::flash('error', 'Please fill in all required fields.');
            Session::flash('old', $_POST);
            Response::redirect('/login');
        }

        $user = User::findByUsername($_POST['username']);
        if ($user === null || !password_verify($_POST['password'], $user['password_hash'])) {
            Session::flash('error', 'Invalid username or password.');
            Session::flash('old', $_POST);
            Response::redirect('/login');
        }

        if (!$user['is_active']) {
            Session::flash('error', 'Your account is not active. Please contact support.');
            Response::redirect('/login');
        }

        Auth::login($user['id'], $user['username'], (bool) $user['is_admin']);
        User::updateLastLogin($user['id']);

        Session::flash('success', 'Welcome back, ' . htmlspecialchars($user['username']) . '!');
        Response::redirect('/desktop');
    }

    public function showRegister(): void
    {
        if (Auth::isLoggedIn()) {
            Response::redirect('/desktop');
        }
        (new ResponseHandler('auth/register.html.twig'))->render();
    }

    public function register(): void
    {
        if (Auth::isLoggedIn()) {
            Session::flash('info', 'You are already logged in.');
            Response::redirect('/desktop');
        }

        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/register');
        }

        $validator = Validator::make($_POST)
            ->required('username')
            ->required('email')
            ->required('password')
            ->required('password_confirm')
            ->email('email')
            ->min('username', 3)
            ->max('username', 50)
            ->min('password', 8)
            ->same('password', 'password_confirm')
            ->unique('username', 'users', 'username');

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/register');
        }

        $emailValidator = Validator::make($_POST)
            ->unique('email', 'users', 'email');

        if (!$emailValidator->validate()) {
            Session::flash('error', 'That email is already registered.');
            Session::flash('validation_errors', $emailValidator->errors());
            Session::flash('old', $_POST);
            Response::redirect('/register');
        }

        $passwordHash = password_hash($_POST['password'], PASSWORD_ARGON2ID);
        $userId = User::create(
            $_POST['username'],
            $_POST['email'],
            $passwordHash
        );

        User::createInitialFolders($userId);

        Session::flash('success', 'Account created! You are now logged in.');
        Auth::login($userId, $_POST['username']);
        Response::redirect('/desktop');
    }

    public function logout(): void
    {
        Auth::logout();
        Session::flash('success', 'You have been logged out.');
        Response::redirect('/');
    }

    public function showForgotPassword(): void
    {
        (new ResponseHandler('auth/forgot-password.html.twig'))->render();
    }

    public function forgotPassword(): void
    {
        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/forgot-password');
        }

        $validator = Validator::make($_POST)
            ->required('email')
            ->email('email');

        if (!$validator->validate()) {
            Session::flash('error', 'Please enter a valid email address.');
            Session::flash('old', $_POST);
            Response::redirect('/forgot-password');
        }

        User::createResetToken($_POST['email']);

        Session::flash('success', 'If an account exists with that email, a reset link has been sent.');
        Response::redirect('/forgot-password');
    }

    public function showResetPassword(): void
    {
        $token = $_GET['token'] ?? '';
        if ($token === '') {
            Session::flash('error', 'Invalid reset link.');
            Response::redirect('/forgot-password');
        }

        $user = User::findByResetToken($token);
        if ($user === null) {
            Session::flash('error', 'Invalid or expired reset link.');
            Response::redirect('/forgot-password');
        }

        (new ResponseHandler('auth/reset-password.html.twig'))
            ->with('token', $token)
            ->render();
    }

    public function resetPassword(): void
    {
        if (!CSRF::validate()) {
            Session::flash('error', 'Invalid form token. Please try again.');
            Response::redirect('/reset-password');
        }

        $token = $_POST['token'] ?? '';
        $validator = Validator::make($_POST)
            ->required('password')
            ->required('password_confirm')
            ->min('password', 8)
            ->same('password', 'password_confirm');

        if (!$validator->validate()) {
            Session::flash('error', 'Please correct the errors below.');
            Session::flash('validation_errors', $validator->errors());
            Session::flash('old', $_POST);
            (new ResponseHandler('auth/reset-password.html.twig'))
                ->with('token', $token)
                ->render();
            return;
        }

        $user = User::findByResetToken($token);
        if ($user === null) {
            Session::flash('error', 'Invalid or expired reset link.');
            Response::redirect('/forgot-password');
        }

        User::updatePassword($user['id'], password_hash($_POST['password'], PASSWORD_ARGON2ID));
        User::clearResetToken($user['id']);

        Session::flash('success', 'Password reset successfully. You may now log in.');
        Response::redirect('/login');
    }
}
