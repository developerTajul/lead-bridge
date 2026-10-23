<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StorePipelineRequest;
use Core\Pipeline\Application\Contracts\PipelineMapperContract;
use Core\Pipeline\Application\Services\PipelineQueryService;
use Core\Pipeline\Application\Services\PipelineService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

final class PipelineController extends Controller
{
    private const ERROR_INVALID = 'PIPELINE_INVALID';

    private const ERROR_NOT_FOUND = 'PIPELINE_NOT_FOUND';

    /**
     * @param PipelineService        $pipelineService
     * @param PipelineQueryService   $pipelineQueryService
     * @param PipelineMapperContract $mapper
     */
    public function __construct(
        private readonly PipelineService $pipelineService,
        private readonly PipelineQueryService $pipelineQueryService,
        private readonly PipelineMapperContract $mapper,
    ) {}

    /**
     * Display a listing of pipelines with their stages.
     *
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $result = $this->pipelineQueryService->getPipelines();

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created pipeline along with its stages.
     *
     * @param StorePipelineRequest $request
     *
     * @return JsonResponse
     */
    public function store(StorePipelineRequest $request): JsonResponse
    {
        // A client sends the stages it means, so an unusable one is a client
        // error rather than something to silently drop.
        try {
            $storePipelineDTO = $this->mapper->mapToStoreDTO($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
                'error_code' => self::ERROR_INVALID,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $result = $this->pipelineService->createPipeline($storePipelineDTO);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ], Response::HTTP_CREATED);
    }

    /**
     * Maps a domain error code to the HTTP status the client should see.
     * A broken pipeline invariant is a validation failure, not a bad request.
     *
     * @param string|null $errorCode
     *
     * @return int
     */
    private function errorStatus(?string $errorCode): int
    {
        if ($errorCode === self::ERROR_NOT_FOUND) {
            return Response::HTTP_NOT_FOUND;
        }

        if ($errorCode === self::ERROR_INVALID) {
            return Response::HTTP_UNPROCESSABLE_ENTITY;
        }

        return Response::HTTP_BAD_REQUEST;
    }

    /**
     * Display the specified pipeline.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $result = $this->pipelineQueryService->getPipelineDetails($id);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ], Response::HTTP_OK);
    }

    /**
     * Display the pipeline with stages and nested deals for Kanban Board.
     *
     * @param string $id
     *
     * @return JsonResponse
     */
    public function kanban(string $id): JsonResponse
    {
        $result = $this->pipelineQueryService->getKanbanBoard($id);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
                'error_code' => $result->errorCode,
            ], $this->errorStatus($result->errorCode));
        }

        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $result->getData(),
        ], Response::HTTP_OK);
    }
}