<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Finance\Models\Lender;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class LenderController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('finance.view'), 403);

        $lenders = Lender::query()
            ->when($request->string('search')->toString(), fn ($q, $s) => $q->where('name', 'like', "%{$s}%")->orWhere('code', 'like', "%{$s}%"))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/lenders/Index', [
            'lenders' => $lenders,
            'filters' => ['search' => $request->string('search')->toString()],
            'can' => [
                'create' => $request->user()->can('finance.create'),
                'update' => $request->user()->can('finance.update'),
                'delete' => $request->user()->can('finance.delete'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->can('finance.create'), 403);

        Lender::query()->create($this->validated($request));

        return back()->with('success', 'Lender created.');
    }

    public function update(Request $request, Lender $lender): RedirectResponse
    {
        abort_unless($request->user()->can('finance.update'), 403);

        $lender->update($this->validated($request, $lender));

        return back()->with('success', 'Lender updated.');
    }

    public function destroy(Request $request, Lender $lender): RedirectResponse
    {
        abort_unless($request->user()->can('finance.delete'), 403);

        $lender->delete();

        return back()->with('success', 'Lender removed.');
    }

    private function validated(Request $request, ?Lender $lender = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:bank,nbfc,captive,other'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'base_interest_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $data['code'] = $lender?->code ?? 'LND-'.strtoupper(Str::random(6));

        return $data;
    }
}
