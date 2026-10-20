<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Infrastructure\Http\{Request, Response};
use App\Support\Env;

final class TasksController
{
    public function drain(Request $req): Response
    {
        if (!$this->authorized($req)) return Response::json(['error'=>'Forbidden'], 403);
        $limit = max(1, min(500, (int)($req->query['limit'] ?? 100)));
        $budget = max(50, min(2000, (int)($req->query['budget_ms'] ?? 400)));
        $svc = new \App\Application\WebhookService();
        $n = $svc->drainPending($limit, $budget);
        return Response::json(['drained' => $n]);
    }

    public function retry(Request $req): Response
    {
        if (!$this->authorized($req)) return Response::json(['error'=>'Forbidden'], 403);
        $limit = max(1, min(500, (int)($req->query['limit'] ?? 100)));
        $maxAttempts = max(1, min(10, (int)($req->query['max_attempts'] ?? 5)));
        $svc = new \App\Application\WebhookService();
        $n = $svc->retryDueForwards($limit, $maxAttempts);
        return Response::json(['retried' => $n]);
    }

    public function cleanup(Request $req): Response
    {
        if (!$this->authorized($req)) return Response::json(['error'=>'Forbidden'], 403);
        $svc = new \App\Application\LogRetentionService();
        $result = $svc->cleanup();
        return Response::json(['cleanup' => $result]);
    }

    private function authorized(Request $req): bool
    {
        $secret = (string)Env::get('TASKS_SECRET', '');
        if ($secret === '') return false;
        $provided = (string)($req->header('X-Tasks-Secret') ?? ($req->query['token'] ?? ''));
        if (!hash_equals($secret, $provided)) return false;
        $whitelist = trim((string)Env::get('TASKS_IP_WHITELIST', ''));
        if ($whitelist !== '') {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '';
            if (!$this->ipAllowed($ip, $whitelist)) return false;
        }
        return true;
    }

    private function ipAllowed(string $ip, string $list): bool
    {
        $parts = array_filter(array_map('trim', explode(',', $list)));
        foreach ($parts as $item) {
            if ($item === $ip) return true;
            if (preg_match('#^\d+\.\d+\.\d+\.\d+/\d+$#', $item)) {
                if ($this->ipv4InCidr($ip, $item)) return true;
            }
        }
        return false;
    }

    private function ipv4InCidr(string $ip, string $cidr): bool
    {
        [$subnet, $mask] = explode('/', $cidr, 2);
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) return false;
        $mask = (int)$mask;
        $ipDec = ip2long($ip);
        $subnetDec = ip2long($subnet);
        $maskDec = -1 << (32 - $mask);
        $subnetDec &= $maskDec;
        return ($ipDec & $maskDec) === $subnetDec;
    }
}
