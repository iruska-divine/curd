<?php
/**
 * Author: zsw iszsw@qq.com
 *
 */

namespace iszsw\curd\lib;

use iszsw\curd\Helper;
use surface\Component;
use surface\Factory;
use surface\helper\Read;
use surface\helper\TableAbstract;
use surface\table\components\Button;
use surface\table\components\Column;
use surface\table\components\Selection;
use surface\table\Table;
use surface\table\Type;
use iszsw\curd\model\Table as TableModel;

class ResolveTable extends Resolve
{

    /**
     * 默认配置
     *
     * @var array
     */
    private $options;

    /**
     * 列项
     *
     * @var array
     */
    private $column;

    /**
     * 按钮操作
     *
     * @var array
     */
    private $buttons;

    /**
     * @var Table
     */
    private $surfaceTable;

    /**
     * 获取列
     *
     * @return array<Component>
     */
    public function getColumn()
    {
        if (is_null($this->column))
        {
            $this->surfaceTable = Factory::table();
            $this->resolveColumn();
        }

        return $this->column;
    }

    protected $hasSearch;

    public function hasSearch($force = false): bool
    {
        if ($force || null === $this->hasSearch)
        {
            // columns
            foreach ($this->table['fields'] as $k => $f)
            {
                if ($f['table_type'] !== '_')
                {
                    $this->hasSearch = true;
                    break;
                }
            }
        }

        return $this->hasSearch;
    }

    /**
     * 列解析
     */
    private function resolveColumn(): void
    {
        $this->column = [];
        // Selection
        $this->column[] = new Selection($this->table['pk']);

        // columns
        foreach ($this->table['fields'] as $k => $f)
        {
            if ($f['table_type'] == '_')
            {
                continue;
            }

            switch ($f['option_type'])
            {
                case "option_relation":
                    $prop = $this->joinName($f['option_relation'][0], $f['option_relation'][2]);
                    break;
                default:
                    $prop = $f['field'];
            }

            $type = $f['table_type'];
            $label = $f['title'];
            $props = array_merge(Helper::paramsFormat($f['table_extend'] ?? []), ["sortable" => $f['table_sort'] ? true : false,]);
            $options = [];

            switch ($f['table_type'])
            {
                case 'select':
                    $options = $this->options($f[$f['option_type']] ?? '', $f['option_type']);
                    break;
                default:
            }

            $this->column[] = $this->generateTable($type, $prop, $label, $props, $options);
        }

        // handle
        $this->column[] = (new Column('_options', '操作'))->props('fixed', 'right')->scopedSlots($this->getButtons(TableModel::LOCAL_RIGHT));
    }

    private function getButtons($local = TableModel::LOCAL_TOP)
    {
        if (is_null($this->buttons))
        {
            $this->resolveButton();
        }

        return $this->buttons[$local] ?? [];
    }

    private function generateTable($type, $prop, $label, $props, array $options = []): Component
    {
        $component = $this->surfaceTable->column($prop, $label);
        $component->props($props);
        switch ($type)
        {
            case 'select':
            case 'switcher':
            case 'writable':
                $child = $this->surfaceTable->$type()->props(
                    [
                        'options'     => $options,
                        'async'   => [
                            'method' => 'post',
                            'data'   => ['id'],
                            'url'    => Helper::builder_table_url('page/change/'.$this->table['table']),
                        ],
                        'doneRefresh' => true,
                    ]
                );
                break;
            case 'expand':
                $component->props(['type' => 'expand']);
            default:
                $child = (new Component())->el('span')->inject(['domProps' => 'innerHTML']);
                break;
        }
        $component->scopedSlots([$child]);

        return $component;
    }

    /**
     * 按钮解析
     */
    private function resolveButton(): void
    {
        $button = $this->table['button'];
        $this->buttons = [
            TableModel::LOCAL_TOP   => [],
            TableModel::LOCAL_RIGHT => [],
        ];

        if (count($this->table['fields']) > 0)
        {
            $i = 0;
            $fields = array_values($this->table['fields']);
            while (1)
            {
                if ( ! isset($fields[$i]))
                {
                    break;
                }
                if ($fields[$i]['search_type'] !== "_")
                {
                    array_unshift(
                        $button, [
                                   "icon"         => "el-icon-search",
                                   "title" => TableModel::$labels['search'],
                                   "button_local" => TableModel::LOCAL_TOP,
                                   "top_event"    => TableModel::BTN_EVENT_SEARCH,
                               ]
                    );
                    break;
                }
                $i++;
            }
            unset($fields);
        }

        $btn = array_reverse($this->table['button_default']);
        foreach ($btn as $v)
        {
            switch ($v)
            {
                case TableModel::BUTTON_CREATE:
                    array_unshift(
                        $button, [
                                   "doneRefresh"  => true,
                                   "icon"        => "el-icon-plus",
                                   "title"       => TableModel::$buttonDefaultLabels[TableModel::BUTTON_CREATE],
                                   "button_local" => TableModel::LOCAL_TOP,
                                   "top_event"    => TableModel::BTN_EVENT_PAGE,
                                   "url"          => Helper::builder_table_url('page/create/'.$this->table['table']),
                               ]
                    );
                    break;
                case TableModel::BUTTON_UPDATE:
                    array_unshift(
                        $button, [
                                   "doneRefresh"  => true,
                                   "icon"        => "el-icon-edit-outline",
                                   "title"       => TableModel::$buttonDefaultLabels[TableModel::BUTTON_UPDATE],
                                   "button_local" => TableModel::LOCAL_RIGHT,
                                   "right_event"  => TableModel::BTN_EVENT_PAGE,
                                   "data_extend"  => [$this->table['pk'],],
                                   "url"          => Helper::builder_table_url('page/update/'.$this->table['table']),
                               ]
                    );
                    break;
                case TableModel::BUTTON_DELETE:
                    array_unshift(
                        $button, [
                                   "doneRefresh"  => true,
                                   "icon"        => "el-icon-close",
                                   "title"       => TableModel::$buttonDefaultLabels[TableModel::BUTTON_DELETE],
                                   "button_local" => TableModel::LOCAL_TOP,
                                   "top_event"    => TableModel::BTN_EVENT_SUBMIT,
                                   "confirm_msg"  => "确认删除？",
                                   "url"          => Helper::builder_table_url('page/delete/'.$this->table['table']),
                               ]
                    );
                    array_unshift(
                        $button, [
                                   "doneRefresh"  => true,
                                   "icon"        => "el-icon-close",
                                   "title"       => TableModel::$buttonDefaultLabels[TableModel::BUTTON_DELETE],
                                   "button_local" => TableModel::LOCAL_RIGHT,
                                   "right_event"  => TableModel::BTN_EVENT_CONFIRM,
                                   "confirm_msg"  => "确认删除？",
                                   "data_extend"  => [$this->table['pk']],
                                   "url"          => Helper::builder_table_url('page/delete/'.$this->table['table']),
                               ]
                    );
                    break;
                case TableModel::BUTTON_REFRESH:
                    array_unshift(
                        $button, [
                                   "icon"         => "el-icon-refresh",
                                   "title" => TableModel::$buttonDefaultLabels[TableModel::BUTTON_REFRESH],
                                   "button_local" => TableModel::LOCAL_TOP,
                                   "top_event"    => TableModel::BTN_EVENT_REFRESH,
                               ]
                    );
                    break;
            }
        }

        foreach ($button as $b)
        {
            $this->buttons[$b['button_local']][] = $this->generateButton($b)->props(
                'doneRefresh', isset($b['doneRefresh']) ? $b['doneRefresh'] : true
            );
        }
    }

    private function generateButton(array $param): Button
    {
        $param = array_merge(
               [
                "icon"         => "el-icon-setting",
                "title" => 'title',
                "button_local" => "right",
                "right_event"  => "page",
                "url"          => '',
                "data_extend"  => [],
                "btn_extend"   => [],
            ], $param
        );

        $btn = $this->surfaceTable->button($param['icon'], $param['title']);
        $type = $param[$param['button_local'].'_event'];
        switch ($type)
        {
            case TableModel::BTN_EVENT_PAGE:
                $btn->createPage($param['url'], $param['data_extend'])->props('doneRefresh', true);
                break;
            case TableModel::BTN_EVENT_CONFIRM:
                $btn->createConfirm($param['confirm_msg'] ?? '', ['method' => 'post', 'data' => $param['data_extend'], 'url' => $param['url']]);
                break;
            case TableModel::BTN_EVENT_REFRESH:
                $btn->createRefresh();
                break;
            case TableModel::BTN_EVENT_SEARCH:
                $btn->createSearch();
                break;
            case TableModel::BTN_EVENT_SUBMIT:
                $btn->createSubmit(
                       [
                        'method' => 'post',
                        'data'   => $param['data_extend'],
                        'url'    => $param['url'],
                    ], $param['confirm_msg'] ?? '', $this->table['pk']
                );
                break;
            default:
                $btn->props('handler', $type);
        }

        if (count($param['btn_extend']) > 0)
        {
            $btn->props('prop', array_merge($btn->props['prop'] ?? [], $param['btn_extend']));
        }

        return $btn;
    }

    public function getHeader(): ?Component
    {
        $children = $titleChildren = [];
        if ($this->table['title'])
        {
            $titleChildren[] = (new Component())->el('span')->class('title')->children([$this->table['title']]);
        }
        if ($this->table['description'])
        {
            $titleChildren[] = (new Component())->el('span')->class('describe')->children([$this->table['description']]);
        }
        if (count($titleChildren))
        {
            $children[] = (new Component())->el('p')->children($titleChildren);
        }

        $children = array_merge($children, $this->getButtons(TableModel::LOCAL_TOP));

        return count($children) ? (new Component(['el' => 'div']))->children($children) : null;
    }

    public function getOptions(?TableAbstract $table = null): array
    {
        if (is_null($this->options))
        {
            $this->options = $this->table['extend'] ?? [];
            $this->options = isset($this->options['props'])
                ? $this->options
                : [
                    'props' => count($this->options) > 0 ? $this->options : [],
                ];
        }

        if ( ! ($this->table['page'] ?? true) && $table)
        {
            $condition = Read::initSearchConditions($table);
            $this->options['props']['data'] = $this->getData($condition['where'], '', null)['list'];
        }

        return $this->options;
    }

    public function getPagination($def = null): ?Component
    {
        return $this->table['page'] ?? false ? $def : null;
    }

    public function getData($where = [], $order = '', $page = null, $limit = 15): array
    {
        $fields = [];
        $relation = [];
        $remote_relation = [];
        foreach ($this->table['fields'] as $field => $config)
        {
            if ($config['table_type'] === '_')
            {
                continue;
            }

            if ($config['relation'])
            {
                $remote_relation[] = $field;
                continue;
            }

            $fields[] = $field;

            if ($config['option_type'] === 'option_relation')
            {
                $relation[] = $field;
            }
        }

        $where = array_filter($where, function ($w)
        {
            return isset($this->table['fields'][$w[0]]) && $this->table['fields'][$w[0]]['search_type'] !== "_";
        });

        $model = Model::instance()->name($this->table['table'])->where($where);
        $count = $model->count();

        if (false === array_search($this->table['pk'], $fields))
        {
            $fields[] = $this->table['pk'];
        }

        $lists = $model->field($fields)->order($order ?: $this->table['pk'].' DESC');
        if ($page !== null)
        {
            $lists = $lists->page($page, $limit);
        }
        $lists = $lists->select()->toArray();
        $lists = array_combine(array_column($lists, $this->table['pk']), $lists);

        $relationData = [];
        if (count($relation))
        {
            foreach ($relation as $f)
            {
                $fieldInfo = $this->table['fields'][$f];
                [$join_table, $join_pk, $join_title] = $fieldInfo['option_relation'];

                $relation_pks = array_values(array_unique(array_filter(array_column($lists, $f))));

                $relationData[$f] = Model::instance()->name($join_table)->whereIn($join_pk, $relation_pks)->column($join_title, $join_pk);
            }
        }
        unset($relation);

        if (count($remote_relation))
        {
            foreach ($remote_relation as $f)
            {
                $fieldInfo = $this->table['fields'][$f];

                $_orr = $fieldInfo['option_remote_relation'];

                $relation_pks = array_values(array_filter(array_column($lists, $_orr[1])));

                $relationData[$f] = Model::instance()->name($_orr[0])->alias($_orr[0])
                    ->field("{$_orr[4]}.*, {$_orr[0]}.{$_orr[2]} as {$this->joinName($_orr[0], $_orr[2])}, {$_orr[0]}.{$_orr[3]} as {$this->joinName($_orr[0], $_orr[3])}")
                    ->leftJoin("{$_orr[4]} {$_orr[4]}", "{$_orr[0]}.{$_orr[3]} = {$_orr[4]}.{$_orr[5]}")
                    ->whereIn("{$_orr[0]}.{$_orr[2]}", $relation_pks)
                    ->select()
                    ->toArray();

            }
        }
        unset($remote_relation);

        foreach ($lists as &$list)
        {
            foreach ($this->table['fields'] as $field => $config)
            {
                if ($config['table_type'] === '_')
                {
                    continue;
                }

                $value = $list[$field] ?? '';

                switch ($config["option_type"])
                {
                    case 'option_remote_relation':
                        $_orr = $config['option_remote_relation'];
                        $_key = $this->joinName($_orr[0], $_orr[2]);
                        $_pk = $list[$_orr[1]];
                        $allow = array_filter(
                            $relationData[$field], function ($v) use ($_key, $_pk)
                        {
                            return $v[$_key] === $_pk;
                        }
                        );
                        $value = array_values(array_column($allow, $_orr[6]));
                        break;
                    case 'option_relation':
                        $fieldInfo = $this->table['fields'][$field];
                        [$join_table, $join_pk, $join_title] = $fieldInfo['option_relation'];
                        $list[$this->joinName($join_table, $join_title)] = $relationData[$field][$list[$field]] ?? '';
                        break;
                    case 'option_config':
                        $value = $config[$config['option_type']][$value] ?? '';
                        break;
                    case 'option_lang':
                        $optionConfig = $config[$config['option_type']];
                        $option = lang('?'.$optionConfig) ? lang($optionConfig) : '';
                        $value = is_array($option) ? $option[$value] : $option;
                        break;
                }

                if ($config["table_format"] ?? false)
                {
                    $value = $this->invoke($config["table_format"], $value, $list);
                }
                $list[$field] = $this->format($value);
            }
        }
        unset($list);

        return [
            'count' => $count,
            'list'  => array_values($lists),
        ];
    }

    private function format($val)
    {
        if (is_array($val))
        {
            return json_encode($val, JSON_UNESCAPED_UNICODE);
        } elseif (is_numeric($val))
        {
            return $val;
        }

        return (string)$val;
    }

    private function joinName($table, $title): string
    {
        return "{$table}__{$title}";
    }

}