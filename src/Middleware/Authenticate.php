<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Auth;
use App\Response;
use App\Session;

final class Authenticate
{
    public function handle(): bool
    {
        if (!Auth::isLoggedIn()) {
            Session::flash('error', 'Please log in to access that page.');
            Response::redirect('/login');
            return false;
        }
        return true;
    }
}
