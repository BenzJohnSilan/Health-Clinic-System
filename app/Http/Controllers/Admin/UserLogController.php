<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UserLog;

class UserLogController extends Controller
{
    public function index()
    {
        $logs = UserLog::with('user')
            ->latest()
            ->paginate(10);

        return view('admin.user-logs', compact('logs'));
    }
}