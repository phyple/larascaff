<?php

namespace Phyple\Larascaff\Databases;

use Closure;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Phyple\Essential\Patterns\Singleton;
use Phyple\Larascaff\Exceptions\EmptyWhereClauseException;
use Throwable;

class Repository extends Singleton
{
    /**
     * Connection used for this repository
     *
     * @var string $connection_name
     */
    protected string $connection_name;

    /**
     * Database table name used for this repository
     *
     * @var string $table_name
     */
    protected string $table_name;

    /**
     * Query builder instance
     *
     * @var Builder $builder
     */
    protected Builder $builder;

    /**
     * Database connection instance used in this repository
     *
     * @var Connection $connection
     */
    protected Connection $connection;

    /**
     * parameter separator token
     *
     * @var string $parameter_separator
     */
    protected string $parameter_separator = ':';

    /**
     * @param array $selects
     * @param array $where
     * @param string|array $order_by
     * @param string|array $group_by
     * @param string|null $limit
     * @param int|null $offset
     * @param array $special_parameters
     * @param bool $distinct
     * @return Collection
     */
    public function find(
        array        $selects = [],
        array        $where = [],
        string|array $order_by = [],
        string|array $group_by = [],
        ?string      $limit = null,
        ?int         $offset = null,
        array        $special_parameters = [],
        bool         $distinct = true
    ): Collection
    {
        if (empty($selects)) $selects = $this->defaultSelect();

        $query = $this->createFindQuery(
            $selects,
            $where,
            $order_by,
            $group_by,
            $limit,
            $offset,
            $special_parameters
        )->addDefaultJoinClause($this->builder())->builder();

        return $distinct ? $query->distinct()->get() : $query->get();
    }

    /**
     * Set default select value in repository
     *
     * Note: Insert your default table select here
     *
     * @return string[]
     */
    protected function defaultSelect(): array
    {
        return ['*'];
    }

    /**
     * Get query builder property
     *
     * @return Builder
     */
    public function builder(): Builder
    {
        if (!isset($this->builder)) $this->createBuilder();

        return $this->builder;
    }

    /**
     * Create new builder instance
     *
     * @param string|null $table_name
     * @param string|null $connection_name
     * @return $this
     */
    public function createBuilder(?string $table_name = null, ?string $connection_name = null): self
    {
        if (!is_null($table_name)) $this->table($table_name);
        if (!is_null($connection_name)) $this->connection($connection_name);

        $this->builder = $this->connection->table($this->table_name);

        return $this;
    }

    /**
     * Set table name for this repository
     *
     * @param string $table_name
     * @return $this
     */
    public function table(string $table_name): self
    {
        $this->table_name = $table_name;

        return $this;
    }

    /**
     * Set connection name property for this repository, and create new
     * connection instance
     *
     * @param string $connection_name
     * @return $this
     */
    public function connection(string $connection_name = 'default'): self
    {
        $this->connection_name = $connection_name;
        $this->connection = DB::connection($connection_name);

        return $this;
    }

    /**
     * Add default join clause into query builder
     *
     * Note: Insert your default join method here using $builder->join() method
     * @param Builder $builder
     * @return $this
     */
    public function addDefaultJoinClause(Builder $builder): self
    {
        return $this;
    }

    /**
     * Create find query
     *
     * @param array $selects
     * @param array $where
     * @param string|array $order_by
     * @param string|array $group_by
     * @param string|null $limit
     * @param int|null $offset
     * @param array $special_parameters
     * @return self
     */
    public function createFindQuery(
        array        $selects = [],
        array        $where = [],
        string|array $order_by = [],
        string|array $group_by = [],
        ?string      $limit = null,
        ?int         $offset = null,
        array        $special_parameters = []
    ): self
    {
        return $this
            ->addSelectClause($selects)
            ->addWhereClause($where)
            ->addOrderByClause($order_by)
            ->addGroupByClause($group_by)
            ->addLimitClause($limit)
            ->addOffsetClause($offset)
            ->resolveSpecialParameters($special_parameters);
    }

    /**
     * Resolve provided special parameters
     *
     * @param array $special_parameters
     * @return $this
     */
    public function resolveSpecialParameters(array $special_parameters = []): self
    {
        return $this;
    }

    /**
     * Add offset clause into query builder
     *
     * @param int|null $offset
     * @return $this
     */
    protected function addOffsetClause(?int $offset = null): self
    {
        $this->builder()->offset($offset ?? config('repository.default_offset', 0));

        return $this;
    }

    /**
     * Add limit clause into query builder
     *
     * @param string|null $limit
     * @return $this
     */
    protected function addLimitClause(?string $limit = null): self
    {
        if (is_null($limit) || strtolower($limit) != "all") {
            $this->builder()->limit($limit ?? config('repository.default_limit', 10));
        }

        return $this;
    }

    /**
     * Add group by clause into query builder
     *
     * @param string|array $group_by
     * @return $this
     */
    protected function addGroupByClause(string|array $group_by = []): self
    {
        if (!is_array($group_by)) $group_by = [$group_by];

        foreach ($group_by as $column) {
            $this->builder()->groupBy($this->resolvePrefixedColumn($column));
        }

        return $this;
    }

    /**
     * Resolve prefixed column with table name
     *
     * @param string $column
     * @return string
     */
    protected function resolvePrefixedColumn(string $column): string
    {
        return !str_contains($column, '.') ? $this->table_name . '.' . $column : $column;
    }

    /**
     * Add order by clause into query builder
     *
     * @param string|array $order_by
     * @return $this
     */
    protected function addOrderByClause(string|array $order_by = []): self
    {
        $order_by = is_array($order_by) ? $order_by : [$order_by];

        foreach ($order_by as $column => $direction) {
            [$column, $direction] = $this->resolveOrderParamQuery($column, $direction);

            $this->builder()->orderBy($this->resolvePrefixedColumn($column), $direction);
        }

        return $this;
    }

    /**
     * Resolve order by parameter before add id into query builder
     *
     * @param int|string $column
     * @param string $direction
     * @return array
     */
    protected function resolveOrderParamQuery(int|string $column, string $direction): array
    {
        if (is_integer($column)) $column = $direction;

        if (str_contains($direction, $this->parameter_separator)) {
            [$column, $direction] = explode($this->parameter_separator, $direction, 2);
        }

        $direction = strtolower($direction) == 'desc' ? 'desc' : 'asc';

        return [$column, $direction];
    }

    /**
     * Add where clause into query builder
     *
     * @param array $parameters
     * @return $this
     */
    protected function addWhereClause(array $parameters = []): self
    {
        foreach ($parameters as $column => $parameter) {
            $column = $this->resolvePrefixedColumn($column);

            if (is_array($parameter)) {
                foreach ($parameter as $param) {
                    $this->buildWhereClause($column, $param);
                }
            } else {
                $this->buildWhereClause($column, $parameter);
            }
        }

        return $this;
    }

    /**
     * Build where clause before added into query builder
     *
     * @param string $column
     * @param string $parameter
     * @return void
     */
    protected function buildWhereClause(string $column, string $parameter): void
    {
        [$operator, $value] = $this->resolveParamQueryValue($parameter);

        if ($operator === 'null') {
            $value == 'true' ?
                $this->builder()->whereNull($column) :
                $this->builder()->WhereNotNull($column);
        } else {
            $this->builder()->where($column, $operator, $value);
        }
    }

    /**
     * @param string $parameter
     * @param string $operator
     * @return array
     */
    protected function resolveParamQueryValue(string $parameter, string $operator = '='): array
    {
        $value = $parameter;
        if (str_contains($parameter, $this->parameter_separator)) {
            [$type, $value] = explode($this->parameter_separator, $parameter, 2);
            $type = strtolower($type);
            $operator = $this->resolveWhereParamOperator($type, $operator);
        }

        return [$operator, $value];
    }

    /**
     * Resolve where param based on param type
     *
     * @param string $type
     * @param string $default
     * @return string
     */
    protected function resolveWhereParamOperator(string $type, string $default = '='): string
    {
        return [
            'neq' => '!=',
            'lt' => '<',
            'lte' => '<=',
            'gt' => '>',
            'gte' => '>=',
            'like' => 'LIKE',
        ][$type] ?? $default;
    }

    /**
     * Add select clause into query builder
     *
     * @param array $selects
     * @return $this
     */
    protected function addSelectClause(array $selects = []): self
    {
        foreach ($selects as $select) {
            if ($select instanceof Expression) {
                $this->builder()->addSelect($select);
                continue;
            }
            $this->builder()->addSelect($this->resolvePrefixedColumn($select));
        }

        return $this;
    }

    /**
     * Add default select table into query builder
     *
     * @param array $merge_select
     * @return $this
     */
    public function addDefaultSelect(array $merge_select = []): self
    {
        $this->addSelectClause(array_merge($this->defaultSelect(), $merge_select));

        return $this;
    }

    /**
     * Perform table join with array
     *
     * @param array $join_clause
     * @return $this
     */
    public function addJoinClauseArray(array $join_clause = []): self
    {
        foreach ($join_clause as $value) {
            $method = $value['method'] ?? $value[0] ?? 'join';
            $table = $value['table'] ?? $value[1] ?? '';
            $first = $value['first'] ?? $value[2] ?? '';
            $operator = $value['operator'] ?? $value[3] ?? '';
            $second = $value['second'] ?? $value[4] ?? '';
            $type = $value['type'] ?? $value[5] ?? '';
            $where = $value['where'] ?? $value[6] ?? '';

            $this->builder()->$method($table, $first, $operator, $second, $type, $where);
        }
        return $this;
    }

    /**
     * Update data
     *
     * @param array $data
     * @param bool $return_data
     * @return Repository|Collection
     * @throws Throwable
     */
    public function create(array $data = [], bool $return_data = true): self|Collection
    {
        return $this->wrapIntoTransaction(
            function () use ($data, $return_data) {

                $this->builder()->insert($data);
                $this->connection->commit();

                return (!$return_data) ? $this : $this->builder()->get();
            }
        );
    }

    /**
     * Wrap function into database transaction
     *
     * @param Closure $closure
     * @return mixed
     * @throws Throwable
     */
    protected function wrapIntoTransaction(Closure $closure): mixed
    {
        try {
            $this->connection->beginTransaction();
            return $closure();
        } catch (Throwable $throwable) {
            $this->connection->rollBack();
            throw $throwable;
        }
    }

    /**
     * Delete database record
     *
     * @param array $parameters
     * @param bool $return_data
     * @param bool $force_empty_where
     * @return Collection|$this
     * @throws Throwable
     */
    public function delete(array $parameters = [], bool $return_data = true, bool $force_empty_where = false): self|Collection
    {
        return $this->wrapIntoTransaction(
            function () use ($parameters, $return_data, $force_empty_where) {
                if (!empty($parameters)) $this->changeBuilderWhereClause($parameters);

                if (empty($this->builder()->wheres) && !$force_empty_where) {
                    throw new EmptyWhereClauseException();
                }

                $result = $return_data ? $this->builder()->get() : $this;
                $this->builder()->delete();
                return $result;
            }
        );
    }

    /**
     * Change where parameters in builder
     *
     * @param array $wheres
     * @return void
     */
    protected function changeBuilderWhereClause(array $wheres = []): void
    {
        $this->builder()->wheres = [];
        $this->builder()->bindings['where'] = [];

        foreach ($wheres as $where => $value) {
            $this->builder()->where($this->resolvePrefixedColumn($where), $value);
        }
    }

    /**
     * Update data
     *
     * @param array $where
     * @param array $updated_data
     * @param bool $return_data
     * @param bool $force_empty_where
     * @return Repository|Collection
     * @throws Throwable
     */
    public function update(array $updated_data = [], array $where = [], bool $return_data = true, bool $force_empty_where = false): self|Collection
    {
        return $this->wrapIntoTransaction(
            function () use ($where, $updated_data, $return_data, $force_empty_where) {

                if (!empty($where)) $this->changeBuilderWhereClause($where);

                if (empty($this->builder()->wheres) && !$force_empty_where) {
                    throw new EmptyWhereClauseException();
                }

                $this->builder()->update($updated_data);
                $this->connection->commit();

                if (!$return_data) return $this;

                $this->changeBuilderWhereClause($updated_data);

                return $this->builder()->get();
            }
        );
    }

    /**
     * Empty table record
     *
     * @throws Throwable
     */
    public function truncate(): void
    {
        $this->wrapIntoTransaction(
            function () {
                $this->builder()->truncate();
            }
        );
    }
}
