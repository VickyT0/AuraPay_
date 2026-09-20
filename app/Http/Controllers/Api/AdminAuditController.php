<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;

class AdminAuditController extends Controller
{
    public function index()
    {
        return response()->json(
            AuditLog::query()
                ->latest()
                ->paginate(50)
        );
    }
}