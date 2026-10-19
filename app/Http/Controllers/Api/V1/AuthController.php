<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Requests\Auth\UserLoginRequest;
use App\Models\User as UserModel;
use Core\Auth\Application\Contracts\AuthMapperContract;
use Core\Auth\Application\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly AuthMapperContract $mapper,
    ){}


    /**
     * register a newly created resource in storage.
     */
    public function register(RegisterRequest $request)
    {
        $data = $this->mapper->mapToRegisterDTO($request->validated());

        $result = $this->authService->register($data);


        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
            ], 422);
        }

        /** @var \Core\Auth\Application\DTOs\Responses\AuthResponseDTO $dto */
        $dto = $result->getData();
        return response()->json([
            'status' => 'success',
            'message' => $result->getMessage(),
            'data' => $dto
        ], 201);
    }

    /**
     * login a newly created resource in storage.
     */
    public function login(UserLoginRequest $request)
    {
        $data = $this->mapper->mapToLoginDTO($request->validated());

        $result = $this->authService->login($data);

        if ($result->isFailure()) {
            return response()->json([
                'status' => 'error',
                'message' => $result->getMessage(),
            ], 422);
        }

        /** @var \Core\Auth\Application\DTOs\Responses\AuthResponseDTO $dto */
        $dto = $result->getData();

        // In-memory user model so Sanctum can mint a token without a DB round trip.
        $user = new UserModel();
        $user->id = $dto->id;
        $user->exists = true;

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => $result->getMessage(),
            'data'    => [
                'token' => $token,
                'user'  => $dto,
            ],
        ], 200);
        

    }


    /**
     * Revoke the current access token.
     */
    public function logout(Request $request)
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Successfully logged out.',
        ], 200);
    }



    public function me(Request $request): JsonResponse
    {
        $result = $this->authService->getProfile((int) $request->user()->id);

        return response()->json([
            'status' => 'success',
            'data'   => $result->getData(),
        ]);
    }


}
