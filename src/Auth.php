<?php

declare(strict_types=1);

namespace App;

final class Auth
{
    public static function login(int $userId, string $username, bool $isAdmin = false): void
    {
        Session::regenerate();
        Session::set('user_id', $userId);
        Session::set('username', $username);
        Session::set('logged_in', true);
        Session::set('is_admin', $isAdmin);
    }

    public static function logout(): void
    {
        Session::destroy();
    }

    public static function getId(): ?int
    {
        return Session::has('user_id') ? (int) Session::get('user_id') : null;
    }

    public static function getUsername(): ?string
    {
        return Session::has('username') ? (string) Session::get('username') : null;
    }

    public static function isLoggedIn(): bool
    {
        return Session::has('logged_in') && Session::get('logged_in') === true;
    }

    public static function isAdmin(): bool
    {
        if (!self::isLoggedIn()) {
            return false;
        }
        return (bool) Session::get('is_admin', false);
    }

    /**
     * Get full user data from database.
     */
    public static function getUser(): ?array
    {
        $userId = self::getId();
        if ($userId === null) {
            return null;
        }
        return \App\Models\User::findById($userId);
    }

    public static function check(): void
    {
        if (!self::isLoggedIn()) {
            Session::flash('error', 'Please log in to access that page.');
            Response::redirect('/login');
        }
    }

    public static function checkAdmin(): void
    {
        self::check();
        if (!self::isAdmin()) {
            Session::flash('error', 'Admin access required.');
            Response::redirect('/');
        }
    }
}
