<?php

namespace Slowlyo\OwlSystemBackup;

use Slowlyo\OwlAdmin\Extend\ServiceProvider;

class OwlSystemBackupServiceProvider extends ServiceProvider
{
    public function settingForm()
    {
        return $this->baseSettingForm()
            ->api('post:' . admin_url('owl-menu-backup'))
            ->initApi('get:' . admin_url('owl-menu-backup'))
            ->wrapWithPanel(false)
            ->actions([])
            ->reload('owl-menu-backup-list')
            ->tabs([
                amisMake()->Tab()->title('添加备份')->body([
                    amisMake()->TextControl('title', '备份名称')->required(),
                    amisMake()->SwitchControl('encrypt', '加密备份')->value(1),
                    amisMake()->CheckboxesControl('needs', '备份项')->checkAll()->inline(false)->options([
                        ['label' => '菜单', 'value' => 'menu'],
                        ['label' => '权限', 'value' => 'permission'],
                        ['label' => '角色', 'value' => 'role'],
                        ['label' => '管理员', 'value' => 'admin'],
                        ['label' => '设置', 'value' => 'setting'],
                        ['label' => '扩展', 'value' => 'extension'],
                        ['label' => '代码生成记录', 'value' => 'code_builder'],
                    ])->required(),
                    amisMake()->Flex()->justify('end')->items([
                        amis('submit')->label('添加备份')->level('success'),
                    ]),
                ]),
                amisMake()->Tab()->title('备份记录')->body([
                    amisMake()->Service()->api('get:' . admin_url('owl-menu-backup'))->id('owl-menu-backup-list')->body([
                        amisMake()->Table()->source('${backups}')->footable()->columns([
                            amisMake()->TableColumn('title', '名称'),
                            amisMake()->TableColumn('file', '文件名'),
                            amisMake()->TableColumn('time', '备份时间'),
                            amisMake()->TableColumn('needs', '备份项')->breakpoint('*'),
                        ])->itemActions([
                            amisMake()
                                ->AjaxAction()
                                ->label('恢复')
                                ->api('put:' . admin_url('owl-menu-backup'))
                                ->confirmText('恢复备份, 将会覆盖现有数据, 确定恢复吗?'),
                            amisMake()
                                ->AjaxAction()
                                ->label('删除')
                                ->api('delete:' . admin_url('owl-menu-backup') . '?file=${file}')
                                ->reload('owl-menu-backup-list')
                                ->confirmText('确定删除吗?'),
                        ]),
                    ]),
                ]),
            ]);
    }
}
