<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Core\Auth\Domain\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shows the dashboard, adapting the content to the user's role.
 */
final class DashboardController extends Controller
{
    /**
     * Displays the dashboard for staff and member users.
     *
     * @param Request $request
     *
     * @return View
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('admin.dashboard', $user);
    }

}