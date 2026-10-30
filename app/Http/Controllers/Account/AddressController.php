<?php

namespace App\Http\Controllers\Account;

use App\Domain\Account\AddressBook;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Carnetul de adrese. Adresele altcuiva răspund 404 (căutarea e mereu după user_id).
 */
class AddressController extends Controller
{
    public function __construct(private readonly AddressBook $book) {}

    public function index(Request $request): View
    {
        return view('account.addresses.index', [
            'addresses' => $this->book->forUser($this->user($request)->id),
            'full' => $this->book->isFull($this->user($request)->id),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->book->isFull($this->user($request)->id)) {
            return $this->full();
        }

        return view('account.addresses.form', ['address' => new Address(['country' => 'RO'])]);
    }

    public function store(AddressRequest $request): RedirectResponse
    {
        $user = $this->user($request);

        if ($this->book->isFull($user->id)) {
            return $this->full();
        }

        $this->book->save($user->id, $request->addressData());

        return redirect()->route('account.addresses.index')->with('status', 'address-saved');
    }

    public function edit(Request $request, int $address): View
    {
        return view('account.addresses.form', ['address' => $this->owned($request, $address)]);
    }

    public function update(AddressRequest $request, int $address): RedirectResponse
    {
        $this->book->save($this->user($request)->id, $request->addressData(), $this->owned($request, $address));

        return redirect()->route('account.addresses.index')->with('status', 'address-saved');
    }

    public function destroy(Request $request, int $address): RedirectResponse
    {
        $this->book->delete($this->owned($request, $address));

        return redirect()->route('account.addresses.index')->with('status', 'address-deleted');
    }

    public function makeDefault(Request $request, int $address): RedirectResponse
    {
        $this->book->makeDefault($this->owned($request, $address));

        return redirect()->route('account.addresses.index')->with('status', 'address-default');
    }

    private function user(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        return $user;
    }

    private function owned(Request $request, int $id): Address
    {
        return $this->book->find($this->user($request)->id, $id) ?? abort(404);
    }

    private function full(): RedirectResponse
    {
        return redirect()->route('account.addresses.index')
            ->withErrors(['limit' => 'Ai atins numărul maxim de adrese ('.AddressBook::LIMIT.'). Șterge una pentru a adăuga alta.']);
    }
}
