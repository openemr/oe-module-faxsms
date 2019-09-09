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
    $menuItem->url="/interface/modules/custom_modules/oe-module-faxsms/messageUI.php";
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


function oe_module_faxsms_patient_report_render_action_buttons(Event $event) {
?>
<button type="button" class="genfax btn btn-default btn-send-msg btn-sm" value="<?php echo xla('Send Fax'); ?>" ><?php echo xlt('Send Fax'); ?></button><span id="waitplace"></span>
<input type='hidden' name='fax' value='0'>
<?php
}
$eventDispatcher->addListener(OpenEMR\Events\PatientReport\PatientReportEvent::ACTIONS_RENDER_POST, 'oe_module_faxsms_patient_report_render_action_buttons');

function oe_module_faxsms_patient_report_render_javascript_post_load(Event $event) {
?>
	function cleanUp(){
	    $("#wait").remove();
	}
	function getFaxContent() {
	    top.restoreSession();
	    document.report_form.fax.value = 1;
	    let url = 'custom_report.php';
	    let wait = '<span id="wait"><?php echo xlt("Building Document .. ");?><i class="fa fa-cog fa-spin fa-2x"></i></span>';
	    $("#waitplace").append(wait);
	    $.ajax({
		type: "POST",
		url: url,
		data: $("#report_form").serialize(),
		success: function (content) {
		    document.report_form.fax.value = 0;
		    let btnClose = <?php echo xlj("Cancel"); ?>;
		    let title = <?php echo xlj("Send To Contact"); ?>;
		    let url = top.webroot_url + '/interface/modules/custom_modules/oe-module-faxsms/contact.php?isContent=0&file=' + content;
		    dlgopen(url, '', 'modal-sm', 525, '', title, {
			buttons: [
			    {text: btnClose, close: true, style: 'default btn-xs'}
			],
			onClosed: 'cleanUp'
		    });
		    return false;
		}
	    }).always(function () {
		$("#wait").remove();
	    });
	    return false;
	}
	$(".genfax").click(function() {getFaxContent();});
<?php
}
$eventDispatcher->addListener(OpenEMR\Events\PatientReport\PatientReportEvent::JAVASCRIPT_READY_POST, 'oe_module_faxsms_patient_report_render_javascript_post_load');
