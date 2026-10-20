<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Http\{Request, Response};

final class HealthController
{
    public function index(Request $req): Response
    {
        return Response::json([
            'status' => 'ok',
            'time' => gmdate('c'),
        ]);
    }
}

