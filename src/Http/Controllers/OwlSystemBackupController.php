<?php

namespace Slowlyo\OwlSystemBackup\Http\Controllers;

use Slowlyo\OwlAdmin\Admin;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Slowlyo\OwlAdmin\Models\Extension;
use Slowlyo\OwlAdmin\Models\AdminSetting;
use Slowlyo\OwlAdmin\Models\AdminCodeGenerator;
use Slowlyo\OwlAdmin\Controllers\AdminController;

class OwlSystemBackupController extends AdminController
{
    private string $path = 'app/backup';

    public function index()
    {
        $this->initDir();

        $needs = [
            'menu'         => '菜单',
            'permission'   => '权限',
            'role'         => '角色',
            'admin'        => '管理员',
            'setting'      => '设置',
            'extension'    => '扩展',
            'code_builder' => '代码生成记录',
        ];

        $backups = collect(scandir(storage_path($this->path)))
            ->filter(fn($file) => !in_array($file, ['.', '..']))
            ->values()
            ->map(function ($item) use ($needs) {
                $content = $this->getContent($item);

                $_needs = data_get($content, 'options.needs', []);
                $_needs = array_map(fn($need) => $needs[$need] ?? $need, $_needs);

                return [
                    'file'  => $item,
                    'title' => data_get($content, 'options.title'),
                    'time'  => data_get($content, 'options.time'),
                    'needs' => $_needs,
                ];
            });

        return $this->response()->success(compact('backups'));
    }

    public function addBackup()
    {
        $this->initDir();

        $needs = explode(',', request('needs'));

        $backup = [];

        if (in_array('menu', $needs)) {
            $backup['menu'] = Admin::adminMenuModel()::query()->get();
        }

        if (in_array('permission', $needs)) {
            $backup['permission'] = Admin::adminPermissionModel()::query()->get();

            if (in_array('menu', $needs)) {
                $_model                    = Admin::adminPermissionModel();
                $backup['permission_menu'] = DB::table((new $_model)->menus()->getTable())->get();
            }
        }

        if (in_array('role', $needs)) {
            $backup['role'] = Admin::adminRoleModel()::query()->get();

            if (in_array('permission', $needs)) {
                $_model                    = Admin::adminRoleModel();
                $backup['role_permission'] = DB::table((new $_model)->permissions()->getTable())->get();
            }
        }

        if (in_array('admin', $needs)) {
            $backup['admin'] = Admin::adminUserModel()::query()->get();

            if (in_array('role', $needs)) {
                $_model               = Admin::adminUserModel();
                $backup['admin_role'] = DB::table((new $_model)->roles()->getTable())->get();
            }
        }

        if (in_array('setting', $needs)) {
            $backup['setting'] = AdminSetting::toBase()->get();
        }

        if (in_array('extension', $needs)) {
            $backup['extension'] = Extension::get();
        }

        if (in_array('code_builder', $needs)) {
            $backup['code_builder'] = AdminCodeGenerator::get();
        }

        $backup['options'] = [
            'needs' => $needs,
            'title' => request('title'),
            'time'  => date('Y-m-d H:i:s'),
        ];

        $filename = 'backup-' . date('YmdHis') . '.bak';

        $content = json_encode($backup);

        if (request('encrypt')) {
            $content = admin_encode($content);
        }

        file_put_contents(storage_path($this->path . '/' . $filename), $content);

        return $this->response()->successMessage('备份成功');
    }

    public function recover()
    {
        $content = $this->getContent(request('file'));

        if (Arr::has($content, 'menu')) {
            Admin::adminMenuModel()::unguard();
            Admin::adminMenuModel()::query()->truncate();
            array_map(fn($item) => Admin::adminMenuModel()::create($item), $content['menu']);
        }

        if (Arr::has($content, 'permission')) {
            Admin::adminPermissionModel()::unguard();
            Admin::adminPermissionModel()::query()->truncate();
            array_map(fn($item) => Admin::adminPermissionModel()::create($item), $content['permission']);

            if (Arr::has($content, 'permission_menu')) {
                $_model = Admin::adminPermissionModel();
                $_table = (new $_model)->menus()->getTable();
                DB::table($_table)->truncate();
                array_map(fn($item) => DB::table($_table)->insert((array)$item), $content['permission_menu']);
            }
        }

        if (Arr::has($content, 'role')) {
            Admin::adminRoleModel()::unguard();
            Admin::adminRoleModel()::query()->truncate();
            array_map(fn($item) => Admin::adminRoleModel()::create($item), $content['role']);

            if (Arr::has($content, 'role_permission')) {
                $_model = Admin::adminRoleModel();
                $_table = (new $_model)->permissions()->getTable();
                DB::table($_table)->truncate();
                array_map(fn($item) => DB::table($_table)->insert((array)$item), $content['role_permission']);
            }
        }

        if (Arr::has($content, 'admin')) {
            Admin::adminUserModel()::unguard();
            Admin::adminUserModel()::query()->truncate();
            array_map(fn($item) => Admin::adminUserModel()::create($item), $content['admin']);

            if (Arr::has($content, 'admin_role')) {
                $_model = Admin::adminUserModel();
                $_table = (new $_model)->roles()->getTable();
                DB::table($_table)->truncate();
                array_map(fn($item) => DB::table($_table)->insert((array)$item), $content['admin_role']);
            }
        }

        if (Arr::has($content, 'setting')) {
            AdminSetting::unguard();
            AdminSetting::query()->truncate();
            array_map(fn($item) => AdminSetting::create($item), $content['setting']);
        }

        if (Arr::has($content, 'extension')) {
            Extension::unguard();
            Extension::query()->truncate();
            array_map(fn($item) => Extension::create($item), $content['extension']);
        }

        if (Arr::has($content, 'code_builder')) {
            AdminCodeGenerator::unguard();
            AdminCodeGenerator::query()->truncate();
            array_map(fn($item) => AdminCodeGenerator::create($item), $content['code_builder']);
        }

        return $this->response()->successMessage('恢复成功');
    }

    public function remove()
    {
        @unlink(storage_path($this->path . '/' . request('file')));

        return $this->response()->successMessage('删除成功');
    }

    public function getContent($path)
    {
        $fileContent = file_get_contents(storage_path($this->path . '/' . $path));
        $content     = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $content = json_decode(admin_decode($fileContent), true);
        }

        return $content;
    }

    public function initDir()
    {
        if (!is_dir(storage_path($this->path))) {
            mkdir(storage_path($this->path));
        }
    }
}
