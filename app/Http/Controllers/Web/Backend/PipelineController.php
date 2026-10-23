<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Backend;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\Pipeline\StorePipelineRequest;
use App\Http\Requests\Web\Pipeline\UpdatePipelineRequest;
use Core\Pipeline\Application\Contracts\PipelineMapperContract;
use Core\Pipeline\Application\Services\PipelineQueryService;
use Core\Pipeline\Application\Services\PipelineService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Shows the pipeline pages and saves pipeline changes.
 */
final class PipelineController extends Controller
{
    /**
     * @param PipelineService        $pipelines
     * @param PipelineQueryService   $pipelineQuery
     * @param PipelineMapperContract $mapper
     */
    public function __construct(
        private readonly PipelineService $pipelines,
        private readonly PipelineQueryService $pipelineQuery,
        private readonly PipelineMapperContract $mapper,
    ) {}

    /**
     * Shows the pipeline list.
     *
     * @return View
     */
    public function index(): View
    {
        $result = $this->pipelineQuery->getPipelines();

        if ($result->isFailure()) {
            return view('admin.pipelines.index', [
                'pipelines' => [],
                'error' => $result->getMessage(),
            ]);
        }

        return view('admin.pipelines.index', ['pipelines' => $result->getData()]);
    }

    /**
     * Shows the create pipeline form.
     *
     * @return View
     */
    public function create(): View
    {
        return view('admin.pipelines.create');
    }

    /**
     * Saves a new pipeline.
     *
     * @param StorePipelineRequest $request
     *
     * @return RedirectResponse
     */
    public function store(StorePipelineRequest $request): RedirectResponse
    {
        // A submitted stage row the mapper cannot read is a bad form value, so
        // it is reported back rather than left to fail the page.
        try {
            $storePipelineDTO = $this->mapper->mapToStoreDTO($request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        $result = $this->pipelines->createPipeline($storePipelineDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.pipelines.index')->with('success', $result->getMessage());
    }

    /**
     * Shows one pipeline.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function show(string $id): View|RedirectResponse
    {
        $result = $this->pipelineQuery->getPipelineDetails($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.pipelines.index')->with('error', $result->getMessage());
        }

        return view('admin.pipelines.show', ['pipeline' => $result->getData()]);
    }

    /**
     * Shows the edit pipeline form.
     *
     * The detail read already carries every stage, so the form is prefilled
     * from it.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function edit(string $id): View|RedirectResponse
    {
        $result = $this->pipelineQuery->getPipelineDetails($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.pipelines.index')->with('error', $result->getMessage());
        }

        return view('admin.pipelines.edit', ['pipeline' => $result->getData()]);
    }

    /**
     * Saves changes to a pipeline.
     *
     * @param UpdatePipelineRequest $request
     * @param string                $id
     *
     * @return RedirectResponse
     */
    public function update(UpdatePipelineRequest $request, string $id): RedirectResponse
    {
        try {
            $updatePipelineDTO = $this->mapper->mapToUpdateDTO((int) $id, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return back()->with('error', $exception->getMessage())->withInput();
        }

        $result = $this->pipelines->update($updatePipelineDTO);

        if ($result->isFailure()) {
            return back()->with('error', $result->getMessage())->withInput();
        }

        return redirect()->route('admin.pipelines.index')->with('success', $result->getMessage());
    }

    /**
     * Shows the Kanban board for one pipeline.
     *
     * @param string $id
     *
     * @return View|RedirectResponse
     */
    public function kanban(string $id): View|RedirectResponse
    {
        $result = $this->pipelineQuery->getKanbanBoard($id);

        if ($result->isFailure()) {
            return redirect()->route('admin.pipelines.index')->with('error', $result->getMessage());
        }

        return view('admin.pipelines.kanban', ['board' => $result->getData()]);
    }
}
