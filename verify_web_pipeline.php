<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Backend\PipelineController;
use Core\Pipeline\Application\Contracts\PipelineMapperContract;
use Core\Pipeline\Application\Services\PipelineQueryService;
use Core\Pipeline\Application\Services\PipelineService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$user = App\Models\User::query()->first();
Auth::guard('web')->setUser($user);
Auth::shouldUse('web');

// 1. The controller resolves from the container.
echo 'CONTROLLER: ' . app(PipelineController::class)::class . PHP_EOL;

// 2. The list read works through the query service.
$queryService = app(PipelineQueryService::class);
$list = $queryService->getPipelines();
echo 'getPipelines success: ' . var_export($list->isSuccess(), true) . PHP_EOL;
echo 'count: ' . count($list->getData()) . PHP_EOL;

$first = $list->getData()[0] ?? null;
echo 'first item type: ' . ($first === null ? 'none' : get_debug_type($first)) . PHP_EOL;
echo 'first id: ' . ($first->id ?? 'n/a') . PHP_EOL;

// 3. The detail read works, and the not-found branch reports cleanly.
if ($first !== null) {
    $detail = $queryService->getPipelineDetails((string) $first->id);
    echo 'getPipelineDetails success: ' . var_export($detail->isSuccess(), true) . PHP_EOL;
    echo 'stages: ' . count($detail->getData()->stages ?? []) . PHP_EOL;
}

$missing = $queryService->getPipelineDetails('999999');
echo 'missing -> failure: ' . var_export($missing->isFailure(), true)
    . ' code=' . $missing->errorCode
    . ' msg=' . $missing->getMessage() . PHP_EOL;

// 4. The index route is reachable once views exist. Report rather than crash.
echo PHP_EOL . 'view exists (index): '
    . var_export(view()->exists('admin.pipelines.index'), true) . PHP_EOL;
echo 'view exists (show): '
    . var_export(view()->exists('admin.pipelines.show'), true) . PHP_EOL;
echo 'route registered: '
    . var_export(Illuminate\Support\Facades\Route::has('admin.pipelines.index'), true) . PHP_EOL;
