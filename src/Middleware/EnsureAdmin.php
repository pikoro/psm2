<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use App\Response;
use App\Session;

final class EnsureAdmin
{
    public function handle(): bool
    {
        if (!Auth::isAdmin()) {
            Session::flash('error', 'Admin access required.');
            Response::redirect('/');
            return false;
        }
        return true;
    }
}
