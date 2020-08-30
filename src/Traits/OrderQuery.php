<?php

namespace Hooshid\QueryMaker\Traits;

trait OrderQuery
{
    /**
     * If manually order by set, we dont overwrite it with default order
     *
     * @var boolean
     */
    protected $setOrderByManually = false;

    /**
     * Add an "order by" clause to the query.
     *
     * @param  string  $column
     * @param  string  $direction
     * @return $this
     */
    public function orderBy($column, $direction = 'desc')
    {
        $this->orders[] = [
            'column' => $column,
            'direction' => $direction
        ];

        $this->setOrderByManually = true;

        return $this;
    }

    /**
     * Resolve orders
     *
     * @return OrderQuery
     */
    private function resolveOrders()
    {
        if ($this->request->get(config('query-maker.parameters.order'))) {
            $this->getOrders($this->request->get(config('query-maker.parameters.order')));
        } elseif(!$this->setOrderByManually) {
            $this->orders = config('query-maker.order');
        }

        array_map([$this, 'addOrderToQuery'], $this->orders);

        return $this;
    }

    /**
     * Get orders
     *
     * @param $order
     */
    private function getOrders($order)
    {
        $orders = array_filter(explode('|', $order));

        array_map([$this, 'appendOrderBy'], $orders);
    }

    /**
     * Append orders global var
     *
     * @param $order
     */
    private function appendOrderBy($order)
    {
        if ($order == 'random') {
            $this->orders[] = 'random';
            return;
        }

        list($column, $direction) = explode(',', $order);

        $this->orders[] = [
            'column' => $column,
            'direction' => $direction
        ];
    }

    /**
     * Add each order to query
     *
     * @param $order
     * @return bool
     */
    private function addOrderToQuery($order)
    {
        if ($order == 'random') {
            return $this->query->inRandomOrder();
        }

        extract($order);

        /** @var string $column */
        /** @var string $direction */
        $this->query->orderBy($column, $direction);

        return true;
    }
}
