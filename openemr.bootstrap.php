<?php
/**
 * Bootstrap custom Fax module.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 *
 * @author    Stephen Nielson <stephen@nielson.org>
 * @copyright Copyright (c) 2019 Stephen Nielson <stephen@nielson.org>
 * @author    Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2019 Jerry Padgett <sjpadgett@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Events\PatientDocuments\PatientDocumentEvent;
use OpenEMR\Events\PatientReport\PatientReportEvent;
use OpenEMR\Events\Messaging\SendSmsEvent;
use OpenEMR\Menu\MenuEvent;
use OpenEMR\Services\Globals\GlobalSetting;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

function oe_module_faxsms_add_menu_item(MenuEvent $event)
{
    $menu = $event->getMenu();

    $menuItem = new stdClass();
    $menuItem->requirement = 0;
    $menuItem->target = 'mod';
    $menuItem->menu_id = 'mod0';
    $menuItem->label = xlt("Fax Module");
    $menuItem->url = "/interface/modules/custom_modules/oe-module-faxsms/messageUI.php";
    $menuItem->children = [];
    $menuItem->acl_req = ["patients", "docs"];
    $menuItem->global_req = ["oefax_enable"];

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
 * @var array                    $module
 * @global                       $eventDispatcher @see ModulesApplication::loadCustomModule
 * @global                       $module          @see ModulesApplication::loadCustomModule
 */

function createFaxModuleGlobals(GlobalsInitializedEvent $event)
{
    $select_array = array(0 => xl('Disabled'), 1 => xl('RingCentral'), 2 => xl('Twilio'));
    $instruct = xl('Enable Fax SMS Support. Remember to setup credentials.');

    $event->getGlobalsService()->createSection("Modules", "Report");
    $setting = new GlobalSetting(xl('Enable Fax SMS Module'), $select_array, 1, $instruct);
    $event->getGlobalsService()->appendToSection("Modules", "oefax_enable", $setting);

    $instruct = xl('Enable Send SMS Support. Various opportunities in GUI.');
    $setting = new GlobalSetting(xl('Enable Send SMS Dialog (Coming Soon)'),'bool', 0, $instruct);
    $event->getGlobalsService()->appendToSection("Modules", "oesms_send", $setting);
}
$eventDispatcher->addListener(MenuEvent::MENU_UPDATE, 'oe_module_faxsms_add_menu_item');
$eventDispatcher->addListener(GlobalsInitializedEvent::EVENT_HANDLE, 'createFaxModuleGlobals');

// patient report send fax button
function oe_module_faxsms_patient_report_render_action_buttons(Event $event)
{
?>
    <button type="button" class="genfax btn btn-secondary btn-send-msg" value="<?php echo xla('Send Fax'); ?>"><?php echo xlt('Send Fax'); ?></button><span id="waitplace"></span>
    <input type='hidden' name='fax' value='0'>
<?php
}
function oe_module_faxsms_patient_report_render_javascript_post_load(Event $event)
{
?>
function getFaxContent() {
    top.restoreSession();
    document.report_form.fax.value = 1;
    let url = 'custom_report.php';
    let wait = '<span id="wait"><?php echo xlt("Building Document .. "); ?><i class="fa fa-cog fa-spin fa-2x"></i></span>';
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
        dlgopen(url, '', 'modal-md', 625, '', title, {buttons: [{text: btnClose, close: true, style: 'secondary'}]});
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
$eventDispatcher->addListener(PatientReportEvent::ACTIONS_RENDER_POST, 'oe_module_faxsms_patient_report_render_action_buttons');
$eventDispatcher->addListener(PatientReportEvent::JAVASCRIPT_READY_POST, 'oe_module_faxsms_patient_report_render_javascript_post_load');

// patient documents fax anchor
function oe_module_faxsms_document_render_action_anchors(Event $event)
{
?>
<a class="btn btn-secondary btn-send-msg" href="" onclick="return doFax(event,file,mime)"><span><?php echo xlt('Send Fax'); ?></span></a>
<?php
}
function oe_module_faxsms_document_render_javascript_fax_dialog(Event $event)
{
?>
function doFax(e, filePath, mime='') {
    e.preventDefault();
    let btnClose = <?php echo xlj("Cancel"); ?>;
    let title = <?php echo xlj("Send To Contact"); ?>;
    let url = top.webroot_url +
    '/interface/modules/custom_modules/oe-module-faxsms/contact.php?isDocuments=true&file=' + filePath +
    '&mime=' + mime;
    dlgopen(url, 'faxto', 'modal-md', 650, '', title, {buttons: [{text: btnClose, close: true, style: 'primary'}]});
    return false;
}
<?php
}
$eventDispatcher->addListener(PatientDocumentEvent::ACTIONS_RENDER_FAX_ANCHOR, 'oe_module_faxsms_document_render_action_anchors');
$eventDispatcher->addListener(PatientDocumentEvent::JAVASCRIPT_READY_FAX_DIALOG, 'oe_module_faxsms_document_render_javascript_fax_dialog');

// send sms button
function oe_module_faxsms_sms_render_action_buttons(Event $event)
{ ?>
<button type="button" class="sendsms btn btn-secondary btn-sm btn-send-msg" onclick="sendSMS('');" value="true"><?php echo xlt('Send SMS'); ?></button>
<?php
}
function oe_module_faxsms_sms_render_javascript_post_load(Event $event)
{ ?>
function sendSMS(phone) {
    let btnClose = <?php echo xlj("Cancel"); ?>;
    let title = <?php echo xlj("Send SMS Message"); ?>;
    let url = top.webroot_url + '/interface/modules/custom_modules/oe-module-faxsms/contact.php?isSMS=1&recipient=' + encodeURIComponent(phone);
    dlgopen(url, '', 'modal-md', 600, '', title, {
        buttons: [{text: btnClose, close: true, style: 'secondary'}]
    });
}
<?php
}
$eventDispatcher->addListener(SendSmsEvent::ACTIONS_RENDER_SMS_POST, 'oe_module_faxsms_sms_render_action_buttons');
$eventDispatcher->addListener(SendSmsEvent::JAVASCRIPT_READY_SMS_POST, 'oe_module_faxsms_sms_render_javascript_post_load');

/*example for sms send*/
/*
<?php
// setup
use OpenEMR\Events\Messaging\SendSmsEvent;
$oesms = !empty($GLOBALS['oefax_enable']) && !empty($GLOBALS['oesms_send'])? 1 : 0;
$smsSendDispatcher = $GLOBALS['kernel']->getEventDispatcher();
//button
if ($oesms) {
    $smsSendDispatcher->dispatch(SendSmsEvent::ACTIONS_RENDER_SMS_POST, new GenericEvent());
}
// function
if ($oesms) {
    $smsSendDispatcher->dispatch(SendSmsEvent::JAVASCRIPT_READY_SMS_POST, new GenericEvent());
}
?>
*/
