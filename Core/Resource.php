<?php
declare(strict_types=1);

namespace App\Core;

/**
 * API Resource — transforms models/arrays into consistent API responses.
 *
 * Usage:
 *   class UserResource extends Resource
 *   {
 *       public function toArray(): array
 *       {
 *           return [
 *               'id'   => $this->resource['id'],
 *               'name' => $this->resource['name'],
 *               'email' => $this->resource['email'],
 *           ];
 *       }
 *
 *       // Sparse fieldsets support
 *       protected array $fields = ['id', 'name', 'email'];
 *   }
 *
 *   // In controller:
 *   return (new UserResource($user))->resolve($request);
 */
abstract class Resource
{
    /**
     * The underlying resource being transformed.
     * @var array|string
     */
    protected array|string $resource;

    /**
     * Request that triggered this transformation (for sparse fieldsets).
     */
    protected ?Request $request = null;

    /**
     * Fields to include when sparse fieldsets are requested.
     * Override in child class: protected array $fields = ['id', 'name'];
     * @var array<string>
     */
    protected array $fields = [];

    /**
     * Create a new resource instance.
     *
     * @param array|string $resource The data to transform
     */
    public function __construct(array|string $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Set the request context (for sparse fieldsets).
     *
     * @param Request $request
     * @return self
     */
    public function withRequest(Request $request): self
    {
        $this->request = $request;
        return $this;
    }

    /**
     * Resolve the resource into an array, applying sparse fieldsets if requested.
     *
     * @return array
     */
    public function resolve(): array
    {
        $data = $this->toArray();

        // Apply sparse fieldsets if ?fields= is present
        if ($this->request !== null && !empty($this->fields)) {
            $fieldsParam = method_exists($this->request, 'getQuery')
                ? $this->request->getQuery('fields')
                : ($this->request['fields'] ?? null);
            if ($fieldsParam !== null && $fieldsParam !== '') {
                $requested = array_map('trim', explode(',', $fieldsParam));
                $data = array_filter($data, fn($key) => in_array($key, $requested), ARRAY_FILTER_USE_KEY);
            }
        }

        return $data;
    }

    /**
     * Transform the resource into an array.
     * Override in child class.
     *
     * @return array
     */
    abstract public function toArray(): array;

    /**
     * Get the raw resource.
     *
     * @return array|string
     */
    public function getResource(): array|string
    {
        return $this->resource;
    }

    /**
     * Helper: wrap a single resource in a response array.
     *
     * @param array $data
     * @return array
     */
    protected function wrap(array $data): array
    {
        return ['data' => $data];
    }

    /**
     * Helper: wrap a collection of resources.
     *
     * @param array $data
     * @param int|null $total
     * @return array
     */
    protected function wrapCollection(array $data, ?int $total = null): array
    {
        $result = ['data' => $data];
        if ($total !== null) {
            $result['meta'] = ['total' => $total];
        }
        return $result;
    }
}

/**
 * ResourceCollection — transforms an array of resources.
 *
 * Usage:
 *   class UserCollection extends ResourceCollection
 *   {
 *       protected string $resourceClass = UserResource::class;
 *   }
 */
class ResourceCollection extends Resource
{
    /**
     * The class of individual resources in this collection.
     * @var string
     */
    protected string $resourceClass;

    /**
     * @param array $resource
     * @param string $resourceClass
     */
    public function __construct(array $resource, string $resourceClass)
    {
        $this->resourceClass = $resourceClass;
        parent::__construct($resource);
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        $results = [];
        foreach ($this->resource as $item) {
            $class = $this->resourceClass;
            $res = new $class($item);
            if ($this->request !== null) {
                $res->withRequest($this->request);
            }
            $results[] = $res->resolve();
        }
        return $results;
    }
}
