<?php

use OpenEMR\Menu\MenuEvent;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use OpenEMR\Core\ModulesApplication;

function oe_module_faxsms_add_menu_item(MenuEvent $event) {
    $menu = $event->getMenu();

    error_log("oefax_add_menu_item ran");
    $menuItem = new \stdClass();
    $menuItem->requirement=0;
    $menuItem->target='mod';
    $menuItem->menu_id='mod0';
    $menuItem->label=xlt("Fax Module");
    $menuItem->url="/interface/modules/custom_modules/oe-module-faxsms/index";
    $menuItem->children = [];

    foreach ($menu as $item) {
        if ($item->menu_id == 'modimg') {
            $item->children[] = $menuItem;
            break;
        }
    }

    $event->setMenu($menu);

    return $event;
}

/**
 * @var EventDispatcherInterface $eventDispatcher
 * @var array $module
 * @global $eventDispatcher @see ModulesApplication::loadCustomModule
 * @global $module @see ModulesApplication::loadCustomModule
 */
$eventDispatcher->addListener(MenuEvent::MENU_UPDATE, 'oe_module_faxsms_add_menu_item');
