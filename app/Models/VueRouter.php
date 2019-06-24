<?php

namespace App\Models;

use App\Utils\Admin;
use Illuminate\Database\Eloquent\Builder;

class VueRouter extends Model
{
    protected $casts = [
        'parent_id' => 'integer',
        'order' => 'integer',
        'cache' => 'bool',
        'menu' => 'bool',
    ];
    protected $fillable = [
        'parent_id',
        'order',
        'title',
        'icon',
        'path',
        'cache',
        'menu',
        'permission',
    ];

    /**
     * 把路由构建成嵌套的数组结构
     *
     * @param bool $withAuth 是否做角色权限筛选
     * @param array $nodes
     * @param int $parentId
     *
     * @return array
     */
    public static function buildNestedArray(bool $withAuth = false, array $nodes = [], $parentId = 0): array
    {
        $branch = [];
        if (empty($nodes)) {
            $nodes = static::query()
                ->when($withAuth, function (Builder $query) {
                    $query->with('roles');
                })
                ->orderBy('order')
                ->get()
                ->toArray();
        }

        static $parentIds;
        $parentIds = $parentIds ?: array_flip(array_column($nodes, 'parent_id'));

        foreach ($nodes as $node) {
            if (
                !$withAuth ||
                (Admin::user()->visible($node['roles']) &&
                    (empty($node['permission']) ?: Admin::user()->can($node['permission'])))
            ) {
                if ($node['parent_id'] == $parentId) {
                    $children = static::buildNestedArray($withAuth, $nodes, $node['id']);

                    if ($children) {
                        $node['children'] = $children;
                    }

                    $branch[] = $node;
                }
            }
        }

        return $branch;
    }

    public function children()
    {
        return $this->hasMany(VueRouter::class, 'parent_id');
    }

    public function delete()
    {
        $this->children->each->delete();
        return parent::delete();
    }

    /**
     * parent_id 默认为 0 处理
     *
     * @param $value
     */
    public function setParentIdAttribute($value)
    {
        $this->attributes['parent_id'] = $value ?: 0;
    }

    public function setPathAttribute($path)
    {
        $this->attributes['path'] = $path ? ('/'.ltrim($path, '/')) : null;
    }

    public function roles()
    {
        return $this->belongsToMany(
            AdminRole::class,
            'vue_router_role',
            'vue_router_id',
            'role_id'
        );
    }
}
