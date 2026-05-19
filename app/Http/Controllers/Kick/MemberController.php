<?php

namespace App\Http\Controllers\Kick;

use App\Http\Controllers\Controller;
use App\Http\Requests\Kick\MemberPermissionUpdateRequest;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MemberController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->where(fn ($q) => $q->whereNotNull('kick_user_id')->orWhere('id', 1))
            ->with('permissions:id,user_id,ability')
            ->orderBy('id')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (User $u): array => [
                'id' => $u->id,
                'name' => $u->name,
                'first_name' => $u->first_name,
                'last_name' => $u->last_name,
                'email' => $u->email,
                'kick_username' => $u->kick_username,
                'phone' => $u->phone,
                'instagram' => $u->instagram,
                'twitter' => $u->twitter,
                'is_super_admin' => $u->isSuperAdmin(),
                'permissions' => $u->permissions->pluck('ability')->all(),
            ]);

        return Inertia::render('kick/Members', [
            'users' => $users,
            'registry' => Permissions::groups(),
        ]);
    }

    public function update(MemberPermissionUpdateRequest $request, User $user): RedirectResponse
    {
        abort_if($user->isSuperAdmin(), 403);

        $user->syncPermissions($request->validated('abilities', []));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Yetkiler güncellendi.')]);

        return back();
    }
}
