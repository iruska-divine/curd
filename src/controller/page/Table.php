<?php

namespace iszsw\curd\controller\page;

use iszsw\curd\lib\ResolveTable;
use surface\Component;
use surface\helper\FormAbstract;
use surface\helper\TableAbstract;
use think\exception\HttpException;

class Table extends TableAbstract
{

    /**
     * @var ResolveTable
     */
    private $table;

    /**
     * @var string
     */
    private $tableName;

    public function __construct($tableName)
    {
        $this->tableName = $tableName;
        $this->table = (new ResolveTable($this->tableName));
    }

    public function search(): ?FormAbstract
    {
        return $this->table->hasSearch() ? new Search($this->tableName) : null;
    }

    public function header(): ?Component
    {
        return $this->table->getHeader();
    }

    public function options(): array
    {
        return $this->table->getOptions($this);
    }

    public function columns(): array
    {
        return $this->table->getColumn();
    }

    public function pagination(): ?Component
    {
        return $this->table->getPagination(parent::pagination());
    }

    public function data($where = [], $order = '', $page = 1, $limit = 15): array
    {
        return $this->table->getData(array_values($where), $order, $page, $limit);
    }


}