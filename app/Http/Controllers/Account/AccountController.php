<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        return view('account.index', [
            'user' => $user,
            'orders' => Order::query()
                ->where('user_id', $user->id)
                ->orderByDesc('placed_at')->orderByDesc('id')
                ->limit(3)
                ->get(),
        ]);
    }

    public function profile(Request $request): View
    {
        return view('account.profile', ['user' => $request->user()]);
    }

    public function password(): View
    {
        return view('account.password');
    }

    public function security(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $pending = $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null;
        $enabled = $user->two_factor_secret !== null && $user->two_factor_confirmed_at !== null;

        return view('account.security', [
            'user' => $user,
            'pending' => $pending,
            'enabled' => $enabled,
            'qrCode' => $pending ? $user->twoFactorQrCodeSvg() : null,
            'setupKey' => $pending ? $this->decrypted($user->two_factor_secret) : null,
            'recoveryCodes' => $enabled ? $this->recoveryCodes($user->two_factor_recovery_codes) : [],
        ]);
    }

    private function decrypted(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        $plain = decrypt($value);

        return is_string($plain) ? $plain : null;
    }

    /**
     * @return list<string>
     */
    private function recoveryCodes(mixed $encrypted): array
    {
        $json = $this->decrypted($encrypted);
        $codes = $json !== null ? json_decode($json, true) : null;

        return is_array($codes) ? array_values(array_filter($codes, 'is_string')) : [];
    }
}
