<?php

namespace App\Middlewares;

use App\Core\Middleware;

class Authenticate extends Middleware
{
    public function handle()
    {
        if (session()->isGuest()) {
            redirect('/login');
        }
    }
}