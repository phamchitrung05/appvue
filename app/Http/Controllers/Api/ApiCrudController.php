<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Prettus\Repository\Contracts\RepositoryInterface;
use Prettus\Repository\Criteria\RequestCriteria;

/**
 * Base controller for JSON CRUD APIs backed by l5-repository.
 *
 * Subclasses only declare the repository interface and validation rules.
 */
abstract class ApiCrudController extends Controller
{
    protected RepositoryInterface $repository;

    public function __construct()
    {
        $this->repository = app($this->repositoryClass());
    }

    /**
     * Fully qualified class name of the repository interface.
     */
    abstract protected function repositoryClass(): string;

    /**
     * Validation rules for store().
     */
    abstract protected function createRules(): array;

    /**
     * Validation rules for update().
     */
    abstract protected function updateRules(): array;

    protected function resourceName(): string
    {
        return class_basename($this->repository->makeModel());
    }

    public function index(Request $request): JsonResponse
    {
        $this->repository->pushCriteria(app(RequestCriteria::class));

        $perPage = (int) $request->query('per_page', (string) config('repository.pagination.limit', 15));

        return response()->json($this->repository->paginate($perPage));
    }

    /**
     * Thêm bản ghi mới sau khi validate dữ liệu đầu vào.
     *
     * @param  Request  $request  Request chứa dữ liệu đã được kiểm tra.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate($this->createRules());

        $item = $this->repository->create($data);

        return response()->json([
            'message' => $this->resourceName().' created.',
            'data' => $item,
        ], 201);
    }

    public function show(int $id): JsonResponse
    {
        try {
            $item = $this->repository->find($id);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'data' => $item,
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate($this->updateRules());

        try {
            $item = $this->repository->update($data, $id);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return response()->json([
            'message' => $this->resourceName().' updated.',
            'data' => $item,
        ]);
    }

    public function destroy(int $id): JsonResponse
    {
        try {
            $this->repository->delete($id);
        } catch (ModelNotFoundException) {
            return $this->notFound();
        }

        return response()->json(null, 204);
    }

    protected function notFound(): JsonResponse
    {
        return response()->json([
            'message' => $this->resourceName().' not found.',
        ], 404);
    }
}
