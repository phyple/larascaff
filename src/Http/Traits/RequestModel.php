<?php

namespace Phyple\Larascaff\Http\Traits;

use Illuminate\Support\Arr;

/**
 * @method get(string $string, int|null $default)
 * @method validated(?string $string = null, mixed $default = null)
 */
trait RequestModel
{
    /**
     * Get request offset parameter
     *
     * @param int|null $default
     * @return int
     */
    public function getOffsetParameters(?int $default = null): int
    {
        return $this->get('offset', $default);
    }

    /**
     * Get limit parameters
     *
     * @param string|null $default
     * @return string|null
     */
    public function getLimitParameters(?string $default = null): ?string
    {
        return $this->validated('limit', $default);
    }

    /**
     * Get order by parameter from request
     *
     * @param array $default
     * @return array
     */
    public function getOrderByParameter(array $default = []): array
    {
        return $this->validated('order_by', $default);
    }

    /**
     * Get group by parameter from request
     *
     * @param array $default
     * @return array
     */
    public function getGroupByParameter(array $default = []): array
    {
        return $this->validated('group_by', $default);
    }

    /**
     * Get validated parameter
     *
     * @return array
     */
    public function getParameter(): array
    {
        return Arr::except($this->validated(), array_merge(
            $this->getSpecialParameters(), [
                'group_by',
                'order_by',
                'limit',
                'offset',
            ]
        ));
    }

    /**
     * Get special parameters if provided
     *
     * @return array
     */
    public function getSpecialParameters(): array
    {
        return [];
    }
}