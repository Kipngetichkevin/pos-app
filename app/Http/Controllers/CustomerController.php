<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\CustomerTransaction;
use App\Models\Shift;
use App\Services\CustomerService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search'));
        $filter = $request->query('filter') === 'all' ? 'all' : 'owing';

        $customers = Customer::query()
            ->when($search, function ($q) use ($search) {
                $term = '%' . $search . '%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('phone', 'like', $term));
            })
            ->when($filter === 'owing', fn ($q) => $q->where('balance', '>', 0))
            ->orderByDesc('balance')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // ?create=1 opens the add form, ?edit=ID the edit form, ?pay=ID the payment form, ?statement=ID the statement
        $editing = $request->query('edit') ? Customer::find($request->query('edit')) : null;
        $paying = $request->query('pay') ? Customer::find($request->query('pay')) : null;
        $statementCustomer = $request->query('statement') ? Customer::find($request->query('statement')) : null;

        return view('customers.index', [
            'customers' => $customers,
            'search' => $search,
            'filter' => $filter,
            'totalOwed' => (float) Customer::where('balance', '>', 0)->sum('balance'),
            'debtors' => Customer::where('balance', '>', 0)->count(),
            'showForm' => $request->boolean('create') || $editing,
            'editing' => $editing,
            'paying' => $paying,
            'statementCustomer' => $statementCustomer,
            'statement' => $statementCustomer
                ? CustomerTransaction::with(['sale', 'user'])->where('customer_id', $statementCustomer->id)->latest()->limit(100)->get()
                : collect(),
            // Where the modals' close / cancel links go: the list with the current search, filter and page
            'listUrl' => route('customers.index', $request->only(['search', 'filter', 'page'])),
        ]);
    }

    public function store(Request $request)
    {
        $customer = new Customer(['balance' => 0]);
        $this->save($request, $customer);

        return redirect($this->listUrl())->with('status', 'Added ' . $customer->name . '.');
    }

    public function update(Request $request, Customer $customer)
    {
        $this->save($request, $customer);

        return redirect($this->listUrl())->with('status', 'Updated ' . $customer->name . '.');
    }

    public function payment(Request $request, Customer $customer)
    {
        $method = (string) $request->input('payMethod');

        $request->validate([
            'payAmount' => 'required|numeric|gt:0|max:' . max((float) $customer->balance, 0.01),
            'payMethod' => 'required|in:cash,mpesa',
            'payReference' => $method === 'mpesa' ? 'required|string|max:50' : 'nullable|string|max:50',
        ], [
            'payAmount.max' => 'This is more than the ' . number_format((float) $customer->balance, 2) . ' owed.',
            'payReference.required' => 'Enter the M-Pesa code.',
        ]);

        $shift = Shift::where('user_id', auth()->id())->where('status', 'open')->latest('opened_at')->first();

        if ($method === 'cash' && ! $shift) {
            return back()->withInput()->withErrors(['payMethod' => 'Open a shift on the POS first: cash goes into your till.']);
        }

        $amount = (float) $request->input('payAmount');

        CustomerService::record($customer, 'payment', -round($amount, 2), [
            'method' => $method,
            'reference' => strtoupper(trim((string) $request->input('payReference'))) ?: null,
            'shift_id' => $shift?->id,
            'notes' => 'Debt payment',
        ]);

        return redirect($this->listUrl())
            ->with('status', 'Received KSh ' . number_format($amount, 2) . ' from ' . $customer->name . '.');
    }

    protected function save(Request $request, Customer $customer): void
    {
        $request->validate([
            'name' => 'required|string|max:150',
            'phone' => ['nullable', 'string', 'max:20', Rule::unique('customers', 'phone')->ignore($customer->id)],
            'email' => 'nullable|email|max:255',
            'kra_pin' => 'nullable|string|max:20',
            'credit_limit' => 'nullable|numeric|min:0',
            'price_level' => 'nullable|in:retail,wholesale',
        ], ['phone.unique' => 'Another customer already has this phone number.']);

        $data = [
            'name' => trim((string) $request->input('name')),
            'phone' => trim((string) $request->input('phone')) ?: null,
            'email' => trim((string) $request->input('email')) ?: null,
            'kra_pin' => strtoupper(trim((string) $request->input('kra_pin'))) ?: null,
            'is_active' => $request->boolean('is_active'),
        ];

        // Only managers can set credit limits
        if (auth()->user()->isManager()) {
            $data['credit_limit'] = (float) ($request->input('credit_limit') ?: 0);
            $data['price_level'] = $request->input('price_level') === 'wholesale' ? 'wholesale' : 'retail';
        }

        $customer->fill($data)->save();
    }

    /**
     * The list the form was opened from, keeping its search, filter and page but dropping the modal flags.
     */
    protected function listUrl(): string
    {
        parse_str((string) parse_url(url()->previous(), PHP_URL_QUERY), $query);

        return route('customers.index', array_intersect_key($query, array_flip(['search', 'filter', 'page'])));
    }
}
