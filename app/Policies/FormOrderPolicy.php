<?php

namespace App\Policies;

use App\Models\FormOrder;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class FormOrderPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, FormOrder $order): bool
    {
        if ($user->isAdmin()) return true;
        return $user->branch_id === $order->branch_id;
    }

    public function create(User $user): bool
    {
        return $user->isBranchUser() && $user->branch_id !== null;
    }

    public function update(User $user, FormOrder $order): bool
    {
        if (! $order->isPending()) return false;
        if ($user->isAdmin()) return true;
        return $user->isBranchManager() && $user->branch_id === $order->branch_id;
    }

    public function delete(User $user, FormOrder $order): bool
    {
        if (! $order->isPending()) return false;
        if ($user->isAdmin()) return true;
        return $user->isBranchManager() && $user->branch_id === $order->branch_id;
    }

    public function approve(User $user, FormOrder $order): bool  { return $user->isAdmin() && $order->canBeApproved(); }
    public function reject(User $user, FormOrder $order): bool   { return $user->isAdmin() && $order->canBeRejected(); }
    public function deliver(User $user, FormOrder $order): bool  { return $user->isAdmin() && $order->canBeDelivered(); }
    public function bill(User $user, FormOrder $order): bool     { return $user->isAdmin() && $order->canBeBilled(); }
}
