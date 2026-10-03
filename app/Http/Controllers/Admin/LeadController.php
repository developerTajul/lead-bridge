<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LeadStoreRequest;
use Core\Lead\Application\Contracts\LeadMapperContract;
use Core\Lead\Application\Contracts\LeadServiceInterface;
use Illuminate\Http\RedirectResponse;

final class LeadController extends Controller
{
    public function __construct(
        private readonly LeadServiceInterface $leads,
        private readonly LeadMapperContract $mapper,
    ) {}

    public function store(LeadStoreRequest $request): RedirectResponse
    {
        $leadCreateDTO = $this->mapper->mapToCreateDTO($request->validated());
        $result = $this->leads->createLead($leadCreateDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->back()->with('success', $result->getMessage());
    }
}
